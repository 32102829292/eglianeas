<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientDisconnection;
use App\Services\ClientGrowthStats;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClientDisconnectionController extends Controller
{
    public function index(Request $request, ClientGrowthStats $stats)
    {
        $relation = config('disconnection.profile_relation');
        $clients = $this->clientsQuery()->with($relation)->get();

        // Date Started from the profile, falling back to when the account was created.
        $startDates = $clients->mapWithKeys(function ($client) use ($relation) {
            $date = $client->{$relation}?->date_started ?? $client->created_at;

            return [$client->getKey() => $date ? Carbon::parse($date)->startOfDay() : null];
        });

        $records = ClientDisconnection::with('client')
            ->whereIn('client_id', $startDates->keys())
            ->orderByDesc('disconnected_at')->orderByDesc('id')
            ->get();

        $reason = $request->query('reason');
        $show = $request->query('show') === 'all' ? 'all' : 'current';

        $rows = $records
            ->when($show === 'current', fn ($c) => $c->whereNull('reconnected_at'))
            ->when(isset(ClientDisconnection::REASONS[$reason]), fn ($c) => $c->where('reason', $reason));

        $chart = $stats->monthly($startDates, $records);
        $disconnectedNow = $records->whereNull('reconnected_at')->unique('client_id')->count();

        return view('admin.clients.disconnected', [
            'rows' => $rows,
            'chart' => $chart,
            'reason' => $reason,
            'show' => $show,
            'connectedNow' => $clients->count() - $disconnectedNow,
            'disconnectedNow' => $disconnectedNow,
            'netChange' => end($chart['connected']) - $chart['connected'][0],
            'canManage' => $request->user()->canManageClients(),
        ]);
    }

    public function store(Request $request, int $client)
    {
        abort_unless($request->user()->canManageClients(), 403);

        $data = $request->validateWithBag('disconnect', [
            'reason' => ['required', Rule::in(array_keys(ClientDisconnection::REASONS))],
            'details' => ['nullable', 'required_if:reason,other', 'string', 'max:1000'],
            'disconnected_at' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'details.required_if' => 'Please explain the reason when choosing Others.',
        ]);

        $done = DB::transaction(function () use ($request, $client, $data) {
            // Lock the client row so a double submit cannot create two open records.
            $model = $this->clientsQuery()->lockForUpdate()->findOrFail($client);

            if (ClientDisconnection::where('client_id', $model->getKey())->whereNull('reconnected_at')->exists()) {
                return false;
            }

            ClientDisconnection::create($data + [
                'client_id' => $model->getKey(),
                'disconnected_by' => $request->user()->getKey(),
            ]);

            return true;
        });

        return redirect()->route('admin.clients.disconnected')
            ->with('success', $done ? 'Client marked as disconnected.' : 'This client is already disconnected.');
    }

    public function reconnect(Request $request, int $client)
    {
        abort_unless($request->user()->canManageClients(), 403);

        $model = $this->clientsQuery()->findOrFail($client);

        ClientDisconnection::where('client_id', $model->getKey())->whereNull('reconnected_at')->update([
            'reconnected_at' => Carbon::today(),
            'reconnected_by' => $request->user()->getKey(),
        ]);

        return redirect()->route('admin.clients.show', $model->getKey())->with('success', 'Client reconnected.');
    }

    private function clientsQuery()
    {
        $query = config('disconnection.client_model')::query();

        if ($column = config('disconnection.role_column')) {
            $query->where($column, config('disconnection.role_value'));
        }

        return $query;
    }
}