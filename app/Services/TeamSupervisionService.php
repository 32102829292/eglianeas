<?php

namespace App\Services;

use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TeamSupervisionService
{
    /**
     * The supervisor plus the staff members the organization chart places
     * under them. This is used for journal visibility and ranking scope.
     *
     * @return Collection<int, int>
     */
    public function teamUserIds(User $supervisor): Collection
    {
        return collect([$supervisor->id])
            ->merge($this->supervisedStaffIds($supervisor))
            ->unique()
            ->values();
    }

    /**
     * Resolve staff accounts from the existing team_members org chart. The
     * chart links accounts through user_id when present and otherwise falls
     * back to the roster name, matching how tracker assignments are linked.
     *
     * @return Collection<int, int>
     */
    public function supervisedStaffIds(User $supervisor): Collection
    {
        $supervisorMember = $this->memberForUser($supervisor);

        if (! $supervisorMember) {
            return collect();
        }

        $supervisedPositions = $this->splitList($supervisorMember->supervises);
        $supervisorPosition = $this->normalize($supervisorMember->position);
        $supervisorName = $this->normalize($supervisorMember->name);

        return TeamMember::query()
            ->whereKeyNot($supervisorMember->getKey())
            ->get()
            ->filter(function (TeamMember $member) use ($supervisedPositions, $supervisorPosition, $supervisorName): bool {
                $position = $this->normalize($member->position);

                if ($position !== '' && $supervisedPositions->contains($position)) {
                    return true;
                }

                $reportsTo = $this->normalize($member->reports_to);

                if ($reportsTo === '') {
                    return false;
                }

                return ($supervisorPosition !== '' && $this->containsPhrase($reportsTo, $supervisorPosition))
                    || ($supervisorName !== '' && $this->containsPhrase($reportsTo, $supervisorName));
            })
            ->map(fn (TeamMember $member): ?User => $this->userForMember($member))
            ->filter(fn (?User $user): bool => $user !== null && $user->isStaff())
            ->map(fn (User $user): int => $user->id)
            ->unique()
            ->values();
    }

    public function supervises(User $supervisor, User $staff): bool
    {
        return $staff->isStaff()
            && $this->supervisedStaffIds($supervisor)->contains($staff->id);
    }

    private function memberForUser(User $user): ?TeamMember
    {
        $linked = TeamMember::query()->where('user_id', $user->id)->first();

        if ($linked) {
            return $linked;
        }

        $members = TeamMember::ordered()->get();

        $exact = $members->first(
            fn (TeamMember $member): bool => $this->normalize($member->name) === $this->normalize($user->name)
        );

        if ($exact) {
            return $exact;
        }

        $matches = $members->filter(
            fn (TeamMember $member): bool => $this->nameMatches($member->name, $user->name)
        );

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function userForMember(TeamMember $member): ?User
    {
        if ($member->user_id) {
            return User::query()->find($member->user_id);
        }

        $users = User::query()
            ->where('role', User::ROLE_STAFF)
            ->whereNull('deleted_at')
            ->get();

        $exact = $users->first(
            fn (User $user): bool => $this->normalize($user->name) === $this->normalize($member->name)
        );

        if ($exact) {
            return $exact;
        }

        $matches = $users->filter(
            fn (User $user): bool => $this->nameMatches($member->name, $user->name)
        );

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function nameMatches(string $rosterName, string $accountName): bool
    {
        $rosterTokens = array_values(array_filter(explode(' ', $this->normalize($rosterName))));
        $accountTokens = array_values(array_filter(explode(' ', $this->normalize($accountName))));

        if ($rosterTokens === [] || $accountTokens === [] || strlen($rosterTokens[0]) < 3) {
            return false;
        }

        $lastStart = count($accountTokens) - count($rosterTokens);

        for ($start = 0; $start <= $lastStart; $start++) {
            $matched = true;

            foreach ($rosterTokens as $offset => $token) {
                if (! str_starts_with($accountTokens[$start + $offset], $token)) {
                    $matched = false;
                    break;
                }
            }

            if ($matched) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, string>
     */
    private function splitList(?string $value): Collection
    {
        if ($value === null || trim($value) === '') {
            return collect();
        }

        return collect(preg_split('/[,;]+/', $value) ?: [])
            ->map(fn (string $item): string => $this->normalize($item))
            ->filter()
            ->unique()
            ->values();
    }

    private function containsPhrase(string $haystack, string $needle): bool
    {
        return preg_match('/(^|\s)'.preg_quote($needle, '/').'(\s|$)/u', $haystack) === 1;
    }

    private function normalize(?string $value): string
    {
        $value = Str::lower((string) $value);
        $value = str_replace('&', ' and ', $value);
        $value = preg_replace('/[^a-z0-9\s]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
