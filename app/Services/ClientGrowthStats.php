<?php

namespace App\Services;

use App\Models\ClientDisconnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ClientGrowthStats
{
    /**
     * @param  Collection  $startDates  [client_id => Carbon|null]
     * @param  Collection  $records     ClientDisconnection rows
     */
    public function monthly(Collection $startDates, Collection $records, int $months = 12): array
    {
        $out = ['labels' => [], 'connected' => [], 'disconnected' => [], 'new' => [], 'lost' => []];
        $out['reasons'] = array_fill_keys(array_keys(ClientDisconnection::REASONS), []);
        $byClient = $records->groupBy('client_id');
        $first = Carbon::today()->startOfMonth()->subMonthsNoOverflow($months - 1);

        for ($i = 0; $i < $months; $i++) {
            $from = $first->copy()->addMonthsNoOverflow($i);
            $to = $from->copy()->endOfMonth();
            $connected = $disconnected = $new = 0;

            foreach ($startDates as $id => $start) {
                if ($start && $start->gt($to)) {
                    continue;
                }
                if ($start && $start->gte($from)) {
                    $new++;
                }
                // Disconnected at month end = a row opened by then and not yet reconnected.
                $isOut = $byClient->get($id, collect())->contains(
                    fn ($r) => $r->disconnected_at->lte($to) && (! $r->reconnected_at || $r->reconnected_at->gt($to))
                );
                $isOut ? $disconnected++ : $connected++;
            }

            $inMonth = $records->filter(fn ($r) => $r->disconnected_at->betweenIncluded($from, $to));

            $out['labels'][] = $from->format('M Y');
            $out['connected'][] = $connected;
            $out['disconnected'][] = $disconnected;
            $out['new'][] = $new;
            $out['lost'][] = $inMonth->count();
            foreach (array_keys($out['reasons']) as $reason) {
                $out['reasons'][$reason][] = $inMonth->where('reason', $reason)->count();
            }
        }

        return $out;
    }
}