<?php

namespace App\Services;

use App\Models\WarrantyGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarrantyGroupOptionService
{
    /**
     * @param  array<int, array<string, mixed>>  $options
     */
    public function sync(WarrantyGroup $warrantyGroup, array $options): void
    {
        $this->validateDefaultOption($options);

        DB::transaction(function () use ($warrantyGroup, $options): void {
            $normalizedOptions = $this->normalizeOptions($options);
            $existingIds = $warrantyGroup->options()->pluck('id')->toArray();
            $submittedIds = collect($normalizedOptions)->pluck('id')->filter()->map(fn ($id) => (int) $id)->toArray();

            $warrantyGroup->options()
                ->whereIn('id', array_diff($existingIds, $submittedIds))
                ->delete();

            foreach ($normalizedOptions as $optionData) {
                $id = $optionData['id'] ?? null;
                unset($optionData['id']);

                if ($id) {
                    $warrantyGroup->options()->whereKey($id)->update($optionData);

                    continue;
                }

                $warrantyGroup->options()->create($optionData);
            }
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     */
    public function validateDefaultOption(array $options): void
    {
        $activeOptions = collect($options)->filter(fn (array $option) => (bool) ($option['is_active'] ?? true));
        $defaultOptions = $activeOptions->filter(fn (array $option) => (bool) ($option['is_default'] ?? false));

        if ($activeOptions->isEmpty()) {
            throw ValidationException::withMessages([
                'warranty_options' => __('Add at least one active warranty option.'),
            ]);
        }

        if ($defaultOptions->count() !== 1) {
            throw ValidationException::withMessages([
                'warranty_options' => __('Choose exactly one active default warranty option.'),
            ]);
        }

        $defaultOption = $defaultOptions->first();

        if ((float) ($defaultOption['price'] ?? 0) !== 0.0) {
            throw ValidationException::withMessages([
                'warranty_options' => __('The default warranty option must be free.'),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeOptions(array $options): array
    {
        return collect($options)
            ->values()
            ->map(fn (array $option, int $index) => [
                'id' => filled($option['id'] ?? null) ? (int) $option['id'] : null,
                'name' => trim((string) ($option['name'] ?? '')),
                'duration_months' => (int) ($option['duration_months'] ?? 0),
                'price' => (float) ($option['price'] ?? 0),
                'description' => filled($option['description'] ?? null) ? (string) $option['description'] : null,
                'is_default' => (bool) ($option['is_default'] ?? false),
                'is_active' => (bool) ($option['is_active'] ?? true),
                'sort_order' => (int) ($option['sort_order'] ?? $index),
            ])
            ->all();
    }
}
