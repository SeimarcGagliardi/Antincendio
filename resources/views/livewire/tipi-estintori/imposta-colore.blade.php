<div class="p-6">
    <h2 class="text-xl font-semibold text-red-600 mb-4">
        Imposta colori per tipologia di estintore
    </h2>

    @if (session()->has('message'))
        <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">
            {{ session('message') }}
        </div>
    @endif

    <div class="bg-white shadow rounded border p-4">
        {{-- Intestazioni --}}
        <div class="grid grid-cols-2 gap-4 border-b pb-2 mb-2 text-sm font-semibold text-gray-600">
            <div>Tipologia estintore</div>
            <div>Colore</div>
        </div>

        {{-- Righe tipologie --}}
        @forelse ($tipi as $tipo)
            @php
                $selectedId = $coloriSelezionati[$tipo->id] ?? null;
                $coloreSel  = $colori->firstWhere('id', $selectedId);
                $hexSel     = $coloreSel->hex ?? '#111827'; // colore testo default (grigio scuro)
            @endphp

            <div class="grid grid-cols-2 gap-4 items-center py-2 border-b last:border-b-0">
                {{-- Colonna sinistra: nome tipo + pallina colore attuale --}}
                <div class="text-sm text-gray-800 flex items-center space-x-2">
                    @if ($tipo->colore)
                        <span class="w-3 h-3 rounded-full border"
                              style="background-color: {{ $tipo->colore->hex }}"></span>
                    @else
                        <span class="w-3 h-3 rounded-full border border-gray-300 bg-white"></span>
                    @endif
                    <span>{{ $tipo->descrizione }}</span>
                </div>

                {{-- Colonna destra: select colore --}}
                <div>
                    <select
                        class="border-gray-300 rounded w-full text-sm"
                        wire:model="coloriSelezionati.{{ $tipo->id }}"
                        style="color: {{ $hexSel }};"
                    >
                        <option value="">— Nessun colore —</option>

                        @foreach ($colori as $colore)
                            <option
                                value="{{ $colore->id }}"
                                style="color: {{ $colore->hex }};"
                            >
                                &#9679; {{ $colore->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500">
                Nessuna tipologia di estintore configurata.
            </p>
        @endforelse

        {{-- Pulsante salva --}}
        <div class="mt-4 flex justify-end">
            <button
                type="button"
                wire:click="salva"
                class="px-4 py-2 bg-red-600 text-white rounded shadow hover:bg-red-700 text-sm"
            >
                Salva modifiche
            </button>
        </div>
    </div>
</div>
