@php($actions = $this->actionsForEntry($entry))

@if ($actions !== [])
    <x-filament-actions::group
        :actions="$actions"
        color="gray"
        dropdown-placement="bottom-end"
        icon="heroicon-m-ellipsis-horizontal"
        icon-button
        label="Actions"
        size="sm"
        tooltip="Entry actions"
    />
@endif
