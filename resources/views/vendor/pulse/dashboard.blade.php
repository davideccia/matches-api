@php
    $sectionHeading = fn (string $title) => '<h3 class="col-span-12 mt-2 first:mt-0 text-lg font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-800 pb-3">'.e($title).'</h3>';
@endphp

<x-pulse>
    {!! $sectionHeading('Infrastructure') !!}

    <livewire:pulse.servers cols="full"/>

    <livewire:pulse.usage cols="12" rows="2"/>

    {!! $sectionHeading('Realtime') !!}

    <livewire:reverb.connections cols="full"/>

    {!! $sectionHeading('Queues & Cache') !!}

    <livewire:pulse.queues cols="4"/>

    <livewire:pulse.cache cols="4"/>

    <livewire:pulse.slow-outgoing-requests cols="4"/>

    {!! $sectionHeading('Performance & Errors') !!}

    <livewire:pulse.slow-queries cols="12"/>

    <livewire:pulse.exceptions cols="6"/>

    <livewire:pulse.slow-requests cols="6"/>

    <livewire:pulse.slow-jobs cols="12"/>
</x-pulse>
