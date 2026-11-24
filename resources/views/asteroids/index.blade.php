
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Asteroid Catalog
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-green-700"
                     role="status">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Filters -->
            <div class="bg-white shadow-sm rounded-xl p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-5">
                    Filter Asteroids
                </h3>

                @if ($errors->any())
                    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4"
                         role="alert">
                        <p class="font-semibold text-red-700">
                            Please correct the following errors:
                        </p>

                        <ul class="mt-2 list-disc list-inside text-sm text-red-600">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="GET" action="{{ route('asteroids.index') }}">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

                        <div>
                            <label for="date_from" class="block text-sm font-medium mb-1">
                                Date From
                            </label>
                            <input type="date" id="date_from" name="date_from"
                                value="{{ $filters['date_from'] ?? '' }}"
                                class="w-full border-gray-300 rounded-lg">
                            @error('date_from')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="date_to" class="block text-sm font-medium mb-1">
                                Date To
                            </label>
                            <input type="date" id="date_to" name="date_to"
                                value="{{ $filters['date_to'] ?? '' }}"
                                class="w-full border-gray-300 rounded-lg">
                            @error('date_to')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="hazardous" class="block text-sm font-medium mb-1">
                                Hazard Status
                            </label>
                            <select id="hazardous" name="hazardous"
                                class="w-full border-gray-300 rounded-lg">
                                <option value="">All Asteroids</option>
                                <option value="1" @selected(($filters['hazardous'] ?? '') === '1')>
                                    Hazardous Only
                                </option>
                                <option value="0" @selected(($filters['hazardous'] ?? '') === '0')>
                                    Safe Only
                                </option>
                            </select>
                            @error('hazardous')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="sort_by" class="block text-sm font-medium mb-1">
                                Sort By
                            </label>
                            <select id="sort_by" name="sort_by"
                                class="w-full border-gray-300 rounded-lg">
                                @foreach ([
                                    'distance' => 'Miss Distance',
                                    'velocity' => 'Velocity',
                                    'diameter' => 'Maximum Diameter',
                                ] as $value => $label)
                                    <option value="{{ $value }}"
                                        @selected(($filters['sort_by'] ?? 'distance') === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="sort_direction" class="block text-sm font-medium mb-1">
                                Sort Direction
                            </label>
                            <select id="sort_direction" name="sort_direction"
                                class="w-full border-gray-300 rounded-lg">
                                <option value="asc"
                                    @selected(($filters['sort_direction'] ?? 'asc') === 'asc')>
                                    Ascending
                                </option>
                                <option value="desc"
                                    @selected(($filters['sort_direction'] ?? 'asc') === 'desc')>
                                    Descending
                                </option>
                            </select>
                        </div>

                        <div>
                            <label for="per_page" class="block text-sm font-medium mb-1">
                                Records Per Page
                            </label>
                            <select id="per_page" name="per_page"
                                class="w-full border-gray-300 rounded-lg">
                                @foreach ([10, 20, 50, 100] as $number)
                                    <option value="{{ $number }}"
                                        @selected((int) ($filters['per_page'] ?? 10) === $number)>
                                        {{ $number }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3 mt-6">
                        <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg">
                            Apply Filters
                        </button>

                        <a href="{{ route('asteroids.index') }}"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-5 py-2 rounded-lg">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Asteroid table -->
            <div class="bg-white shadow-sm rounded-xl overflow-hidden">
                <div class="p-6 border-b">
                    <h3 class="text-xl font-bold text-gray-800">
                        Asteroids
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Total found: {{ $asteroids->total() }}
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-sm font-semibold text-gray-700">
                                <th class="px-5 py-4">Asteroid</th>
                                <th class="px-5 py-4">Diameter (m)</th>
                                <th class="px-5 py-4">Min Distance (km)</th>
                                <th class="px-5 py-4">Max Velocity (km/h)</th>
                                <th class="px-5 py-4">Hazard</th>
                                <th class="px-5 py-4">Hazard Score</th>
                                <th class="px-5 py-4">Watchlist</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100">
                            @forelse ($asteroids as $asteroid)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-gray-900">
                                            {{ $asteroid->name }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            NASA ID: {{ $asteroid->nasa_reference_id }}
                                        </p>
                                    </td>

                                    <td class="px-5 py-4">
                                        {{ number_format((float) $asteroid->estimated_diameter_max_m, 2) }}
                                    </td>

                                    <td class="px-5 py-4">
                                        @if ($asteroid->min_distance_km !== null)
                                            {{ number_format((float) $asteroid->min_distance_km, 2) }}
                                        @else
                                            —
                                        @endif
                                    </td>

                                    <td class="px-5 py-4">
                                        @if ($asteroid->max_velocity_kmh !== null)
                                            {{ number_format((float) $asteroid->max_velocity_kmh, 2) }}
                                        @else
                                            —
                                        @endif
                                    </td>

                                    <td class="px-5 py-4">
                                        @if ($asteroid->is_potentially_hazardous)
                                            <span class="text-red-600 font-semibold">
                                                Hazardous
                                            </span>
                                        @else
                                            <span class="text-green-600 font-semibold">
                                                Safe
                                            </span>
                                        @endif
                                    </td>

                                    <td class="px-5 py-4 font-semibold">
                                        {{ number_format((float) $asteroid->calculated_hazard_index, 2) }}
                                    </td>

                                    <td class="px-5 py-4">
                                        @if ($watchedAsteroidIds->contains($asteroid->id))
                                            <span class="inline-flex items-center rounded-lg bg-green-100 px-3 py-2 text-sm font-semibold text-green-700">
                                                ✓ In Watchlist
                                            </span>
                                        @else
                                            <form method="POST" action="{{ route('watchlist.store') }}">
                                                @csrf

                                                <input type="hidden"
                                                       name="asteroid_id"
                                                       value="{{ $asteroid->id }}">

                                                <button type="submit"
                                                        class="whitespace-nowrap rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                                    + Add to Watchlist
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-10 text-center text-gray-500">
                                        No asteroids found for the selected filters.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-6 border-t">
                    {{ $asteroids->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
