<div class="space-y-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold text-red-700">Prezzi Anomalie</h2>
            <p class="text-sm text-gray-600">
                Configura prezzo base, attivazione e regole prezzo per tipologia.
            </p>
        </div>
        <button type="button"
                wire:click.prevent="salvaTutti"
                class="px-3 py-2 rounded border border-red-700 bg-red-700 text-white text-sm hover:bg-red-800">
            Salva Tutto
        </button>
    </div>

    @if(!$hasPrezzoColumn)
        <div class="rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
            Colonna <code>anomalie.prezzo</code> non trovata. Esegui le migration.
        </div>
    @endif

    @if(!$hasFlagTipoEstintoreColumn || !$hasFlagTipoPresidioColumn || !$hasPrezziTipoEstintoreTable || !$hasPrezziTipoPresidioTable)
        <div class="rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
            Struttura prezzi per tipologia non completa. Esegui le ultime migration per attivare la gestione avanzata.
        </div>
    @endif

    @forelse($anomalieByCategoria as $categoria => $anomalieCategoria)
        <div class="rounded border border-gray-200 bg-white shadow-sm">
            <div class="px-4 py-2 border-b bg-gray-50 font-semibold text-gray-700">
                {{ $categoria ?: 'Senza categoria' }}
            </div>

            <div class="divide-y divide-gray-100">
                @foreach($anomalieCategoria as $anomalia)
                    @php
                        $anomaliaId = (int) $anomalia->id;
                        $tipoEstEnabled = (bool) ($usaPrezziTipoEstintore[$anomaliaId] ?? false);
                        $tipoPresEnabled = (bool) ($usaPrezziTipoPresidio[$anomaliaId] ?? false);
                    @endphp
                    <div class="p-3" wire:key="anomalia-prezzo-{{ $anomaliaId }}">
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-start">
                            <div class="lg:col-span-4">
                                <div class="font-medium text-gray-900">{{ $anomalia->etichetta }}</div>
                                <div class="text-xs text-gray-500">ID: {{ $anomaliaId }}</div>
                            </div>

                            <div class="lg:col-span-2">
                                <label class="block text-xs text-gray-600 mb-1">Prezzo base (€)</label>
                                <input type="text"
                                       wire:model.live.debounce.250ms="prezzi.{{ $anomaliaId }}"
                                       wire:blur="salvaRiga({{ $anomaliaId }})"
                                       wire:keydown.enter.prevent="salvaRiga({{ $anomaliaId }})"
                                       class="w-full border rounded px-2 py-1 text-right {{ !empty($invalidPrezzi[$anomaliaId] ?? null) ? 'border-red-500 bg-red-50' : 'border-gray-300' }}"
                                       placeholder="0,00">
                            </div>

                            <div class="lg:col-span-2">
                                <label class="block text-xs text-gray-600 mb-1">Attiva</label>
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input type="checkbox"
                                           wire:model="attive.{{ $anomaliaId }}"
                                           class="h-5 w-5 border-gray-300 rounded">
                                    <span>Disponibile</span>
                                </label>
                            </div>

                            <div class="lg:col-span-3 space-y-1">
                                <label class="block text-xs text-gray-600">Regole prezzo</label>
                                <label class="flex items-center gap-2 text-xs">
                                    <input type="checkbox"
                                           wire:model="usaPrezziTipoEstintore.{{ $anomaliaId }}"
                                           class="h-4 w-4 border-gray-300 rounded">
                                    <span>Prezzi per tipo estintore</span>
                                </label>
                                <label class="flex items-center gap-2 text-xs">
                                    <input type="checkbox"
                                           wire:model="usaPrezziTipoPresidio.{{ $anomaliaId }}"
                                           class="h-4 w-4 border-gray-300 rounded">
                                    <span>Prezzi per tipo presidio (idranti/porte)</span>
                                </label>
                            </div>

                            <div class="lg:col-span-1 flex justify-end">
                                <button type="button"
                                        wire:click.prevent="salvaRiga({{ $anomaliaId }})"
                                        class="px-2 py-1 rounded border border-gray-300 hover:bg-gray-50 text-xs">
                                    Salva
                                </button>
                            </div>
                        </div>

                        @if($tipoEstEnabled)
                            <div class="mt-3 rounded border border-blue-200 bg-blue-50 p-3">
                                <div class="text-xs font-semibold text-blue-900 mb-2">
                                    Prezzi per Tipo Estintore (vuoto = usa prezzo base)
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2">
                                    @foreach($tipiEstintori as $tipo)
                                        @php
                                            $tipoId = (int) $tipo['id'];
                                            $invalidKey = $anomaliaId . ':' . $tipoId;
                                        @endphp
                                        <div class="bg-white border border-blue-100 rounded p-2">
                                            <label class="block text-[11px] text-gray-700 mb-1">{{ $tipo['label'] }}</label>
                                            <input type="text"
                                                   wire:model.lazy="prezziTipoEstintore.{{ $anomaliaId }}.{{ $tipoId }}"
                                                   wire:blur="salvaRiga({{ $anomaliaId }})"
                                                   class="w-full border rounded px-2 py-1 text-right text-sm {{ !empty($invalidPrezziTipoEstintore[$invalidKey] ?? null) ? 'border-red-500 bg-red-50' : 'border-gray-300' }}"
                                                   placeholder="usa base">
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($tipoPresEnabled)
                            <div class="mt-3 rounded border border-emerald-200 bg-emerald-50 p-3">
                                <div class="text-xs font-semibold text-emerald-900 mb-2">
                                    Prezzi per Tipo Presidio (vuoto = usa prezzo base)
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2">
                                    @foreach($tipiPresidio as $tipo)
                                        @php
                                            $tipoId = (int) $tipo['id'];
                                            $invalidKey = $anomaliaId . ':' . $tipoId;
                                        @endphp
                                        <div class="bg-white border border-emerald-100 rounded p-2">
                                            <div class="text-[10px] uppercase tracking-wide text-emerald-700 mb-1">
                                                {{ $tipo['categoria'] ?: 'Presidio' }}
                                            </div>
                                            <label class="block text-[11px] text-gray-700 mb-1">{{ $tipo['label'] }}</label>
                                            <input type="text"
                                                   wire:model.lazy="prezziTipoPresidio.{{ $anomaliaId }}.{{ $tipoId }}"
                                                   wire:blur="salvaRiga({{ $anomaliaId }})"
                                                   class="w-full border rounded px-2 py-1 text-right text-sm {{ !empty($invalidPrezziTipoPresidio[$invalidKey] ?? null) ? 'border-red-500 bg-red-50' : 'border-gray-300' }}"
                                                   placeholder="usa base">
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="rounded border border-gray-200 bg-white p-4 text-sm text-gray-500">
            Nessuna anomalia configurata.
        </div>
    @endforelse
</div>
