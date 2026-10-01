<style>
    :root {
        --primary-color: {{ isset($settings) ? $settings?->primary_color : '#1c64f2' }};
    }

    .bg-primary {
        background-color: var(--portal-primary, var(--primary-color));
    }

    .bg-primary-darken {
        background-color: var(--portal-primary, var(--primary-color));
        filter: brightness(90%);
    }

    .text-primary {
        color: var(--portal-primary, var(--primary-color));
    }
</style>
