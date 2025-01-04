import preset from './vendor/filament/support/tailwind.config.preset'
import tailwindcssAnimated from 'tailwindcss-animated';
import tailwindcssIntersect from 'tailwindcss-intersect';

export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './app/Livewire/**/*.php',
        './resources/views/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
    plugins: [tailwindcssAnimated, tailwindcssIntersect],
}
