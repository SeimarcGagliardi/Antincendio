<div class="space-y-4" x-data>
  {{-- Header con azioni --}}
  <div class="flex items-center justify-between">
    <h2 class="text-xl font-semibold">Colore per tipologia estintore</h2>

    <div class="flex items-center gap-2">
      @php $dirty = count($this->changedIds); @endphp

      <button wire:click="resetModifiche"
              class="px-3 py-1.5 rounded border text-sm hover:bg-gray-50"
              @disabled($dirty === 0)
              title="Annulla modifiche">
        Annulla
      </button>

      <button wire:click="salva"
              class="px-3 py-1.5 rounded text-sm text-white
                     {{ $dirty ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-400 cursor-not-allowed' }}"
              @disabled($dirty === 0)
              title="Salva modifiche">
        Salva modifiche
        @if($dirty) <span class="ml-1 text-xs bg-white/20 px-1.5 py-0.5 rounded">{{ $dirty }}</span> @endif
      </button>
    </div>
  </div>

  {{-- Flash --}}
  @if (session()->has('message'))
    <div class="bg-green-50 border border-green-200 text-green-800 text-sm px-3 py-2 rounded">
      <i class="fa fa-check-circle mr-1"></i> {{ session('message') }}
    </div>
  @endif

  <div class="overflow-x-auto rounded-xl border">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-3 py-2 text-left">Sigla</th>
          <th class="px-3 py-2 text-left">Descrizione</th>
          <th class="px-3 py-2 text-left">Tipo</th>
          <th class="px-3 py-2 text-right">Kg</th>
          <th class="px-3 py-2">Colore</th>
        </tr>
      </thead>
      <tbody class="divide-y">
        @foreach($tipi as $t)
          @php
            $coloreId = $selezioni[$t->id] ?? null;
            $hex = $coloreId ? ($hexById[$coloreId] ?? '#9CA3AF') : '#9CA3AF';
            $nome = $coloreId ? ($nomeById[$coloreId] ?? '—') : '—';
            $isDirty = (in_array($t->id, $this->changedIds, true));
          @endphp

          <tr style="border-left: 6px solid {{ $hex }};">
            <td class="px-3 py-2 font-mono">
              {{ $t->sigla }}
              @if($isDirty)
                <span class="ml-1 text-xs text-amber-600">•</span>
              @endif
            </td>
            <td class="px-3 py-2">{{ $t->descrizione }}</td>
            <td class="px-3 py-2">{{ $t->tipo }}</td>
            <td class="px-3 py-2 text-right">{{ $t->kg }}</td>

            <td class="px-3 py-2">
              <div class="relative" x-data="{ open:false }">
                <button type="button"
                        class="w-56 justify-start inline-flex items-center gap-2 px-3 py-1.5 rounded-md border border-gray-300 text-sm hover:bg-gray-50"
                        @click="open = !open"
                        @keydown.escape.window="open=false"
                        :aria-expanded="open"
                        aria-haspopup="listbox">
                  <span class="inline-block w-4 h-4 rounded-full ring-1 ring-black/10" style="background-color: {{ $hex }}"></span>
                  <span>{{ $nome }}</span>
                </button>

                {{-- Dropdown palette personalizzato --}}
                <div x-show="open" x-transition
                     @click.outside="open=false"
                     class="absolute z-10 mt-1 w-56 max-h-64 overflow-auto rounded-md border bg-white shadow">
                  {{-- Opzione "nessuno" --}}
                  <button type="button"
                          class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50"
                          wire:click="setColore({{ $t->id }}, null)"
                          @click="open=false">
                    <span class="inline-block w-4 h-4 rounded-full ring-1 ring-black/10 bg-gray-300"></span>
                    <span>— nessuno —</span>
                  </button>

                  <div class="border-t my-1"></div>

                  @foreach($colori as $c)
                    <button type="button"
                            class="w-full flex items-center gap-2 px-3 py-2 text-left hover:bg-gray-50"
                            wire:click="setColore({{ $t->id }}, {{ $c->id }})"
                            @click="open=false">
                      <span class="inline-block w-4 h-4 rounded-full ring-1 ring-black/10"
                            style="background-color: {{ $c->hex }}"></span>
                      <span>{{ $c->nome }}</span>
                    </button>
                  @endforeach
                </div>
              </div>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
