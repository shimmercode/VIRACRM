@props(['status' => null, 'domain' => 'customer'])

@php
    use App\Enums\ContractStatus;
    use App\Enums\CustomerStatus;
    use App\Models\Project;

    // One component for both domains keeps the colour language identical
    // across pages: green only ever means "healthy/active", amber only ever
    // means "attention needed" (§8, §9).
    if ($domain === 'project') {
        // Project status words differ from contract ones ("پایان‌یافته" means
        // different things), but the colour language is shared: green only
        // ever means healthy, amber only ever means attention.
        $key = is_object($status) ? (string) $status : (string) $status;
        $key = $key === '' ? 'draft' : $key;

        [$label, $tone] = match ($key) {
            'active' => [Project::STATUSES['active'], 'success'],
            'paused' => [Project::STATUSES['paused'], 'warn'],
            'completed' => [Project::STATUSES['completed'], 'info'],
            'cancelled' => [Project::STATUSES['cancelled'], 'danger'],
            default => [Project::STATUSES['draft'], 'neutral'],
        };
    } elseif ($domain === 'contract') {
        $enum = $status instanceof ContractStatus
            ? $status
            : (ContractStatus::tryFrom((string) $status) ?? ContractStatus::Draft);

        [$label, $tone] = match ($enum) {
            ContractStatus::Draft => ['پیش‌نویس', 'neutral'],
            ContractStatus::Active => ['فعال', 'success'],
            ContractStatus::Expiring => ['در آستانه انقضا', 'warn'],
            ContractStatus::Expired => ['پایان‌یافته', 'muted'],
            ContractStatus::Renewed => ['تمدیدشده', 'info'],
            ContractStatus::Cancelled => ['لغو شده', 'danger'],
        };
    } else {
        $enum = $status instanceof CustomerStatus
            ? $status
            : (CustomerStatus::tryFrom((string) $status) ?? CustomerStatus::Lead);

        $label = $enum->label();
        $tone = $enum->tone();
    }
@endphp

<x-badge :tone="$tone" :dot="true">{{ $label }}</x-badge>
