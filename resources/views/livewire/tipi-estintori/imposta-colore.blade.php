<div class="space-y-4">
  <h2 class="text-xl font-semibold">Colore per tipologia estintore</h2>

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
          @php $hex = $t->colore->hex ?? '#9CA3AF'; @endphp
          <tr>
            <td class="px-3 py-2 font-mono">{{ $t->sigla }}</td>
            <td class="px-3 py-2">{{ $t->descrizione }}</td>
            <td class="px-3 py-2">{{ $t->tipo }}</td>
            <td class="px-3 py-2 text-right">{{ $t->kg }}</td>
            <td class="px-3 py-2">
              <div class="flex items-center gap-2">
                <span class="inline-block w-4 h-4 rounded-full ring-1 ring-black/10"
                      style="background-color: {{ $hex }}"></span>
                <select wire:model="selezioni.{{ $t->id }}"
                        class="rounded-md border-gray-300 text-sm">
                  <option value="">— nessuno —</option>
                  @foreach($colori as $c)
                    <option value="{{ $c->id }}">{{ $c->nome }} ({{ $c->hex }})</option>
                  @endforeach
                </select>
              </div>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
