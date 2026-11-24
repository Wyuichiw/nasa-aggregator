<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            NASA Space Dashboard
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            <!-- NASA APOD -->
            <div class="bg-white shadow-sm rounded-xl overflow-hidden">
                <div class="p-6 border-b">
                    <h3 class="text-2xl font-bold text-gray-800">
                        Astronomy Picture of the Day
                    </h3>
                    <p class="text-gray-500 mt-1">
                        Latest available NASA APOD
                    </p>
                </div>

                @if ($apod)
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 p-6">
                        <div>
                            @if ($apod->media_type === 'image')
                                <img
                                    src="{{ $apod->local_storage_path
                                        && \Illuminate\Support\Facades\Storage::disk('public')->exists($apod->local_storage_path)
                                            ? asset('storage/' . $apod->local_storage_path)
                                            : $apod->source_url }}"
                                    alt="{{ $apod->title }}"
                                    class="w-full rounded-lg object-cover"
                                >
                            @else
                                <div class="bg-gray-100 rounded-lg p-8 text-center">
                                    <p class="text-gray-600 mb-3">
                                        NASA Video of the Day
                                    </p>
                                    <a
                                        href="{{ $apod->source_url }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-blue-600 underline"
                                    >
                                        Watch Video
                                    </a>
                                </div>
                            @endif
                        </div>

                        <div>
                            <p class="text-sm text-gray-500 mb-2">
                                {{ $apod->media_date->format('d.m.Y') }}
                            </p>

                            <h4 class="text-xl font-bold text-gray-900 mb-4">
                                {{ $apod->title }}
                            </h4>

                            <p class="text-gray-700 leading-relaxed">
                                {{ strip_tags(html_entity_decode($apod->explanation, ENT_QUOTES | ENT_HTML5, 'UTF-8')) }}
                            </p>

                            @if ($apod->copyright)
                                <p class="text-sm text-gray-500 mt-4">
                                    Copyright: {{ $apod->copyright }}
                                </p>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="p-8 text-center text-gray-500">
                        No NASA APOD data available yet.
                    </div>
                @endif
            </div>

            <!-- Upcoming asteroid approaches -->
            <div class="bg-white shadow-sm rounded-xl overflow-hidden">
                <div class="p-6 border-b">
                    <h3 class="text-2xl font-bold text-gray-800">
                        Upcoming Asteroid Approaches
                    </h3>

                    <p class="text-gray-500 mt-1">
                        Three nearest upcoming approaches to Earth
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold">
                                    Asteroid
                                </th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">
                                    Approach Date
                                </th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">
                                    Velocity (km/h)
                                </th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">
                                    Distance (km)
                                </th>
                                <th class="px-6 py-4 text-left text-sm font-semibold">
                                    Hazard
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100">
                            @forelse ($upcomingApproaches as $approach)
                                <tr>
                                    <td class="px-6 py-4 font-medium">
                                        {{ $approach->asteroid->name }}
                                    </td>

                                    <td class="px-6 py-4">
                                        {{ $approach->close_approach_datetime->format('d.m.Y H:i') }}
                                    </td>

                                    <td class="px-6 py-4">
                                        {{ number_format($approach->relative_velocity_kmh, 2) }}
                                    </td>

                                    <td class="px-6 py-4">
                                        {{ number_format($approach->miss_distance_km, 2) }}
                                    </td>

                                    <td class="px-6 py-4">
                                        @if ($approach->asteroid->is_potentially_hazardous)
                                            <span class="text-red-600 font-semibold">
                                                Hazardous
                                            </span>
                                        @else
                                            <span class="text-green-600 font-semibold">
                                                Safe
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5"
                                        class="px-6 py-8 text-center text-gray-500">
                                        No upcoming asteroid approaches found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
