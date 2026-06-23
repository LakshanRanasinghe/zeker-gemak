<?php

use App\Models\BusinessAvailability;
use Illuminate\Support\Carbon;
use Livewire\Component;

new class extends Component
{
    public int $year;

    public array $availabilityData = [];

    // Modal state
    public string $selectedDateForModal = '';

    public bool $isFullyUnavailable = false;

    public ?string $unavailableStartTime = null;

    public ?string $unavailableEndTime = null;

    public function mount()
    {
        $this->year = (int) request('year', now()->year);
        $this->loadAvailability();

        // Auto-populate weekdays if none exist for the year
        if (empty($this->availabilityData)) {
            $this->selectAllWeekdays();
        }
    }

    public function loadAvailability()
    {
        $this->availabilityData = BusinessAvailability::whereYear('date', $this->year)
            ->get()
            ->keyBy(fn ($d) => $d->date->format('Y-m-d'))
            ->toArray();
    }

    public function toggleDate($dateString)
    {
        // Don't allow weekends
        $date = Carbon::parse($dateString);
        if ($date->isWeekend()) {
            return;
        }

        $availability = $this->availabilityData[$dateString] ?? null;

        if ($availability) {
            // It's currently available. Open modal to set unavailability.
            $this->selectedDateForModal = $dateString;
            $this->isFullyUnavailable = $availability['is_fully_unavailable'] ?? false;
            $this->unavailableStartTime = $availability['unavailable_start_time'] ? Carbon::parse($availability['unavailable_start_time'])->format('H:i') : null;
            $this->unavailableEndTime = $availability['unavailable_end_time'] ? Carbon::parse($availability['unavailable_end_time'])->format('H:i') : null;
            $this->modal('unavailability-modal')->show();
        } else {
            // It's currently unavailable (no record). Clicking makes it fully available.
            BusinessAvailability::create([
                'date' => $dateString,
                'is_fully_unavailable' => false,
                'unavailable_start_time' => null,
                'unavailable_end_time' => null,
            ]);
            $this->loadAvailability();
        }
    }

    public function saveUnavailability()
    {
        if ($this->isFullyUnavailable) {
            BusinessAvailability::where('date', $this->selectedDateForModal)->delete();
        } else {
            BusinessAvailability::updateOrCreate(
                ['date' => $this->selectedDateForModal],
                [
                    'is_fully_unavailable' => false,
                    'unavailable_start_time' => $this->unavailableStartTime ?: null,
                    'unavailable_end_time' => $this->unavailableEndTime ?: null,
                ]
            );
        }

        $this->modal('unavailability-modal')->close();
        $this->loadAvailability();
    }

    public function nextYear()
    {
        $this->year++;
        $this->loadAvailability();
    }

    public function previousYear()
    {
        $this->year--;
        $this->loadAvailability();
    }

    public function setYear($year)
    {
        $this->year = $year;
        $this->loadAvailability();
    }

    public function selectAllWeekdays()
    {
        $startDate = Carbon::create($this->year, 1, 1);
        $endDate = Carbon::create($this->year, 12, 31);

        $datesToInsert = [];
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            if (! $date->isWeekend() && ! isset($this->availabilityData[$date->format('Y-m-d')])) {
                $datesToInsert[] = [
                    'date' => $date->format('Y-m-d'),
                    'is_fully_unavailable' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (! empty($datesToInsert)) {
            BusinessAvailability::insertOrIgnore($datesToInsert);
            $this->loadAvailability();
        }
    }

    public function getMonthsProperty()
    {
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $date = Carbon::create($this->year, $m, 1);
            $daysInMonth = $date->daysInMonth;

            $days = [];
            // Padding for first day of month
            $firstDayOfWeek = $date->dayOfWeekIso; // 1 (Mon) - 7 (Sun)
            for ($i = 1; $i < $firstDayOfWeek; $i++) {
                $days[] = null;
            }

            for ($d = 1; $d <= $daysInMonth; $d++) {
                $currentDate = Carbon::create($this->year, $m, $d);
                $days[] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'day' => $d,
                    'isWeekend' => $currentDate->isWeekend(),
                    'isPast' => $currentDate->isPast() && ! $currentDate->isToday(),
                ];
            }

            $months[] = [
                'name' => $date->translatedFormat('F'),
                'days' => $days,
            ];
        }

        return $months;
    }
};
?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading size="xl" level="1">{{ __('Business Availability') }}</flux:heading>
            <flux:subheading size="lg" class="mb-6">
                {{ __('Select the days when your business is available. Weekends are automatically marked as unavailable.') }}
            </flux:subheading>
        </div>
        
        <div class="flex items-center gap-4">
            <flux:button wire:click="selectAllWeekdays">{{ __('Select all Weekdays') }}</flux:button>
            <flux:button icon="chevron-left" wire:click="previousYear" />
            <span class="text-xl font-semibold">{{ $year }}</span>
            <flux:button icon="chevron-right" wire:click="nextYear" />
            <flux:button wire:click="setYear({{ now()->year }})">{{ __('Current Year') }}</flux:button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @foreach ($this->months as $month)
            <flux:card class="p-4">
                <flux:heading size="md" class="mb-4 text-center">{{ $month['name'] }}</flux:heading>
                
                <div class="grid grid-cols-7 gap-1 text-center text-xs mb-2 font-medium text-zinc-500 dark:text-zinc-400">
                    <div>{{ __('Mo') }}</div>
                    <div>{{ __('Tu') }}</div>
                    <div>{{ __('We') }}</div>
                    <div>{{ __('Th') }}</div>
                    <div>{{ __('Fr') }}</div>
                    <div class="text-red-400 dark:text-red-500">{{ __('Sa') }}</div>
                    <div class="text-red-400 dark:text-red-500">{{ __('Su') }}</div>
                </div>
                
                <div class="grid grid-cols-7 gap-1">
                    @foreach ($month['days'] as $day)
                        @if ($day === null)
                            <div class="aspect-square"></div>
                        @else
                            @php
                                $availability = $this->availabilityData[$day['date']] ?? null;
                                $isChecked = $availability !== null;
                                $isWeekend = $day['isWeekend'];
                                $hasPartialUnavailability = $availability && ($availability['unavailable_start_time'] || $availability['unavailable_end_time']);
                                $isFullyUnavailable = $availability && $availability['is_fully_unavailable'];
                            @endphp
                            
                            <button 
                                wire:click="toggleDate('{{ $day['date'] }}')"
                                class="aspect-square flex flex-col items-center justify-center rounded text-sm transition-colors relative
                                    @if($isWeekend) 
                                        bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500
                                    @elseif($isChecked && !$isFullyUnavailable)
                                        bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600
                                    @else
                                        bg-white border border-zinc-200 text-zinc-700 hover:bg-zinc-50 dark:bg-zinc-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800
                                    @endif
                                "
                                title="{{ $day['date'] }}"
                            >
                                <span>{{ $day['day'] }}</span>
                                @if(!$isWeekend && $isChecked && !$isFullyUnavailable)
                                    <div class="absolute bottom-1 w-1.5 h-1.5 rounded-full {{ $hasPartialUnavailability ? 'bg-amber-400' : 'bg-white' }}"></div>
                                @endif
                            </button>
                        @endif
                    @endforeach
                </div>
            </flux:card>
        @endforeach
    </div>

    <flux:modal name="unavailability-modal" class="md:w-[32rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Mark Unavailability') }}</flux:heading>
                <flux:subheading>{{ __('Specify the hours you are unavailable for :date', ['date' => $selectedDateForModal]) }}</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:checkbox wire:model.live="isFullyUnavailable" label="{{ __('Fully unavailable all day') }}" />

                @if(!$isFullyUnavailable)
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input type="time" wire:model="unavailableStartTime" label="{{ __('Unavailable From') }}" />
                        <flux:input type="time" wire:model="unavailableEndTime" label="{{ __('Unavailable To') }}" />
                    </div>
                @endif
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button wire:click="saveUnavailability" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>