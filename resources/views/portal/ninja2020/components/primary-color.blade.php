<style>
    :root {
        --primary-color: {{ $settings->primary_color }};
    }

    .bg-primary {
        background-color: var(--primary-color);
    }

    .bg-primary-darken {
        background-color: vaR(--primary-color);
        filter: brightness(90%);
    }

    .text-primary {
        color: var(--primary-color);
    }
</style>