{{--
    x-filament-panels::page already renders getHeaderWidgets()/getFooterWidgets()
    automatically (see vendor/filament/filament/resources/views/components/page/index.blade.php),
    so explicitly rendering them again here duplicated every widget on this
    page -- two identical stat-card blocks, two identical response tables,
    each pair showing the same data.
--}}
<x-filament-panels::page>
</x-filament-panels::page>
