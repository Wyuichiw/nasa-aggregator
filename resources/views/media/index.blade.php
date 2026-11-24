
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            NASA Media Library
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Search and filters -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-2">
                    Explore NASA Media
                </h3>

                <p class="text-gray-500 text-sm mb-6">
                    Search astronomy pictures and filter by tags.
                </p>

                @if ($errors->any())
                    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4">
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

                <form method="GET" action="{{ route('media.index') }}">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                        <!-- Fulltext Search -->
                        <div class="md:col-span-2">
                            <label for="search"
                                class="block text-sm font-medium text-gray-700 mb-2">
                                Search Media
                            </label>

                            <input
                                type="text"
                                id="search"
                                name="search"
                                value="{{ $filters['search'] ?? '' }}"
                                placeholder="Search galaxies, planets, nebulae..."
                                maxlength="200"
                                class="w-full border-gray-300 rounded-lg shadow-sm"
                            >
                        </div>

                        <!-- Tag Filter -->
                        <div>
                            <label for="tag"
                                class="block text-sm font-medium text-gray-700 mb-2">
                                Filter by Tag
                            </label>

                            <select
                                id="tag"
                                name="tag"
                                class="w-full border-gray-300 rounded-lg shadow-sm"
                            >
                                <option value="">All Tags</option>

                                @foreach ($tags as $tag)
                                    <option
                                        value="{{ $tag->id }}"
                                        @selected((string) ($filters['tag'] ?? '') === (string) $tag->id)
                                    >
                                        {{ $tag->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3 mt-5">
                        <button
                            type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-5 py-2 rounded-lg"
                        >
                            Search Media
                        </button>

                        <a
                            href="{{ route('media.index') }}"
                            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-5 py-2 rounded-lg"
                        >
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- Media results -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                    <div>
                        <h3 class="text-xl font-bold text-gray-800">
                            Astronomy Gallery
                        </h3>

                        <p class="text-gray-500 text-sm mt-1">
                            Total found: {{ $media->total() }}
                        </p>
                    </div>
                </div>

                @if ($media->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                        @foreach ($media as $item)
                            <article class="border border-gray-200 rounded-xl overflow-hidden bg-white">

                                <!-- Media preview -->
                                <div class="bg-gray-100">
                                    @if ($item->media_type === 'image')
                                        @php
                                            $hasLocalImage = $item->local_storage_path
                                                && \Illuminate\Support\Facades\Storage::disk('public')
                                                    ->exists($item->local_storage_path);

                                            $imageUrl = $hasLocalImage
                                                ? asset('storage/' . $item->local_storage_path)
                                                : $item->source_url;
                                        @endphp

                                        <img
                                            src="{{ $imageUrl }}"
                                            alt="{{ $item->title }}"
                                            loading="lazy"
                                            class="w-full h-56 object-cover"
                                        >
                                    @else
                                        <div class="h-56 flex flex-col items-center justify-center text-gray-600">
                                            <span class="text-4xl mb-3">▶</span>
                                            <span class="font-medium">NASA Video</span>
                                        </div>
                                    @endif
                                </div>

                                <!-- Media details -->
                                <div class="p-5">
                                    <p class="text-sm text-gray-500 mb-2">
                                        {{ $item->media_date->format('d.m.Y') }}
                                    </p>

                                    <h4 class="text-lg font-bold text-gray-900 mb-3">
                                        {{ $item->title }}
                                    </h4>

                                    <p class="text-gray-600 text-sm leading-relaxed mb-4">
                                        {{ \Illuminate\Support\Str::limit(
                                            strip_tags(html_entity_decode(
                                                $item->explanation,
                                                ENT_QUOTES | ENT_HTML5,
                                                'UTF-8'
                                            )),
                                            180
                                        ) }}
                                    </p>

                                    <!-- Tags -->
                                    @if ($item->tags->isNotEmpty())
                                        <div class="flex flex-wrap gap-2 mb-4">
                                            @foreach ($item->tags as $tag)
                                                <a
                                                    href="{{ route('media.index', ['tag' => $tag->id]) }}"
                                                    class="bg-blue-50 text-blue-700 text-xs px-3 py-1 rounded-full hover:bg-blue-100"
                                                >
                                                    {{ $tag->name }}
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if ($item->copyright)
                                        <p class="text-xs text-gray-500 mb-3">
                                            © {{ $item->copyright }}
                                        </p>
                                    @endif

                                    @if ($item->source_url)
                                        <a
                                            href="{{ $item->source_url }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-block text-blue-600 hover:text-blue-800 font-medium text-sm"
                                        >
                                            View Original Media
                                        </a>
                                    @endif
                                </div>
                            </article>
                        @endforeach

                    </div>

                    <!-- Pagination -->
                    <div class="mt-8">
                        {{ $media->links() }}
                    </div>
                @else
                    <div class="py-12 text-center">
                        <p class="text-gray-500 text-lg">
                            No NASA media found.
                        </p>

                        <p class="text-gray-400 text-sm mt-2">
                            Try another search term or reset the filters.
                        </p>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
