<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{ config('app.name') }}
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
            © {{ date('Y') }} {{ config('app.name') }}. @lang('All rights reserved.')

            This email was sent from a no-reply address. Please don't reply — the inbox isn't monitored and you won't receive a response.

            View our Terms of Service: {{ route('legal.terms') }}
            Privacy Policy: {{ route('legal.privacy') }}
            Cookie Policy: {{ route('legal.cookies') }}
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
