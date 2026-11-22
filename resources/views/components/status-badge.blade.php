@props(['status' => null, 'domain' => 'customer'])

@php
    use App\Enums\ContractStatus;
    use App\Enums\CustomerStatus;

    // One component for both domains keeps the colour language identical
    // across pages: green only ever means "healthy/active", amber only ever
    // means "attention needed" (§8, §9).
    if ($domain === 'contract') {
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
