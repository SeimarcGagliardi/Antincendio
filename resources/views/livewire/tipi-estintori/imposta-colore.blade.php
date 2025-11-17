{{-- resources/views/clienti/imposta-colore.blade.php --}}
@extends('layouts.app')

@section('content')
    <div class="max-w-5xl mx-auto py-6 space-y-6">
        {{-- TITOLO / BREADCRUMB / ECC. --}}
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold text-gray-800">
                Imposta colore cliente
            </h1>

            <a href="{{ route('clienti.index') }}"
               class="text-sm text-gray-500 hover:text-red-600">
                ← Torna all'elenco clienti
            </a>
        </div>

        {{-- QUI C'È IL TUO CONTENUTO ATTUALE --}}
        <div class="bg-white shadow rounded-lg p-6">
            {{-- Se usi un componente Livewire per questa pagina, ad esempio: --}}
            @livewire('clienti.imposta-colore', ['cliente' => $cliente])

            {{-- Oppure qui ci metti pure il resto del codice Blade che hai già --}}
        </div>

        {{-- ========================================================= --}}
        {{-- BLOCCHETTO DI TEST PER ALPINE                         --}}
        {{-- ========================================================= --}}
        <div 
            x-data="{ open: false }"
            class="mt-8 p-4 border border-dashed border-blue-400 rounded-lg bg-blue-50"
        >
            <p class="text-sm text-gray-700 mb-2">
                Test Alpine: se Alpine funziona, cliccando il bottone qui sotto dovrebbe comparire / scomparire il box verde.
            </p>

            <button
                type="button"
                @click="open = !open"
                class="px-3 py-1.5 text-sm font-medium rounded bg-blue-600 text-white hover:bg-blue-700"
            >
                Toggle Alpine
            </button>

            <div 
                x-show="open"
                x-transition
                class="mt-3 px-3 py-2 rounded bg-green-100 text-green-800 text-sm"
            >
                ✅ Alpine sta funzionando su questa pagina.
            </div>
        </div>
    </div>
@endsection
