<div class="p-4">
    <h1 class="text-2xl font-bold mb-4 text-red-700">
        {{ $utenteId ? 'Modifica Utente' : 'Nuovo Utente' }}
    </h1>

    <form wire:submit.prevent="save" class="space-y-4 max-w-xl">
        <div>
            <label>Nome</label>
            <input type="text" wire:model="name" class="input input-bordered w-full" />
            @error('name') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
        </div>

        <div>
            <label>Email</label>
            <input type="email" wire:model="email" class="input input-bordered w-full" />
            @error('email') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
        </div>

        <div>
            <label>Password {{ $utenteId ? '(lascia vuota per non modificare)' : '' }}</label>
            <input type="password" wire:model="password" class="input input-bordered w-full" />
            @error('password') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
        </div>

        <div>
            <label>Ruoli</label>
            <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2 border rounded p-3 bg-gray-50">
                @foreach ($ruoli as $ruolo)
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox"
                               wire:model.live="ruolo_ids"
                               value="{{ $ruolo->id }}"
                               class="rounded border-gray-300">
                        <span>{{ $ruolo->nome }}</span>
                    </label>
                @endforeach
            </div>
            @error('ruolo_ids') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
            @error('ruolo_ids.*') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
        </div>

        <div>
            <label>Colore identificativo</label>
            <input type="color" wire:model="colore_ruolo" class="input w-20 h-10 p-1" />
        </div>

        <div>
            <label>Immagine profilo</label>
            <input type="file" wire:model="profile_image" class="file-input file-input-bordered w-full" />
            @error('profile_image') <span class="text-sm text-red-500">{{ $message }}</span> @enderror
        </div>

        <div class="mt-4">
            <button class="btn btn-primary" type="submit">Salva</button>
            <a href="{{ route('utenti.index') }}" class="btn btn-outline ml-2">Annulla</a>
        </div>
    </form>
</div>
