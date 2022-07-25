<style>
    :root {
        --primary-color: {{ isset($settings) ? $settings?->primary_color : '#1c64f2' }};
    }

    .bg-primary {
        background-color: var(--primary-color);
    }

    .bg-primary-darken {
        background-color: var(--primary-color);
        filter: brightness(90%);
    }

    .text-primary {
        color: var(--primary-color);
    }
</style>
