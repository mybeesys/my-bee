import preset from '../../../../vendor/filament/filament/tailwind.config.preset'
// import '../../../..//awcodes/filament-table-repeater/resources/css/plugin.css';

export default {
    presets: [preset],
    content: [
        './app/Filament/Tenant/**/*.php',
        './resources/views/components/**/*.blade.php',
        './resources/views/filament/tenant/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
        './vendor/awcodes/filament-table-repeater/resources/**/*.blade.php',
    ],

}
