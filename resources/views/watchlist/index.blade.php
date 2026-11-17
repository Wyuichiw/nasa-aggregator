
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('My Asteroid Watchlist') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">

                @if (session('success'))
                    <div class="mb-4 text-green-700">
                        {{ session('success') }}
                    </div>
                @endif

                <h3 class="text-lg font-bold mb-4">
                    Add an asteroid
                </h3>

                <form method="POST" action="{{ route('watchlist.store') }}"
                      class="mb-8 space-y-3">
                    @csrf

                    <div>
                        <label for="asteroid_id">Asteroid ID</label>
                        <input
                            id="asteroid_id"
                            type="number"
                            name="asteroid_id"
                            value="{{ old('asteroid_id') }}"
                            class="border rounded p-2 block w-full"
                            required
                        >
                        @error('asteroid_id')
                            <p class="text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="notes">Notes</label>
                        <textarea
                            id="notes"
                            name="notes"
                            class="border rounded p-2 block w-full"
                            maxlength="2000"
                        >{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                            class="bg-blue-600 text-white px-4 py-2 rounded">
                        Add to Watchlist
                    </button>
                </form>

                <h3 class="text-lg font-bold mb-4">
                    Watched Asteroids
                </h3>

                @forelse ($asteroids as $asteroid)
                    <div class="border-b py-4">
                        <p class="font-bold">
                            {{ $asteroid->name }}
                        </p>

                        <p class="text-sm text-gray-600">
                            NASA ID: {{ $asteroid->nasa_reference_id }}
                        </p>

                        <form method="POST"
                              action="{{ route('watchlist.update', $asteroid->pivot->id) }}"
                              class="mt-3">
                            @csrf
                            @method('PATCH')

                            <textarea
                                name="notes"
                                class="border rounded p-2 w-full"
                                maxlength="2000"
                            >{{ $asteroid->pivot->notes }}</textarea>

                            <button type="submit" class="mt-2 bg-gray-700 text-white px-3 py-2 rounded">
                                Save Note
                            </button>
                        </form>

                        <form method="POST"
                              action="{{ route('watchlist.destroy', $asteroid->pivot->id) }}"
                              class="mt-2">
                            @csrf
                            @method('DELETE')

                            <button type="submit"
                                    class="text-red-600">
                                Remove
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="text-gray-500">
                        Your watchlist is empty.
                    </p>
                @endforelse

                <div class="mt-6">
                    {{ $asteroids->links() }}
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
