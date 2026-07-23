<?php

if (! function_exists('tmpfile')) {
    /**
     * Some hosts (e.g. Cloudways) disable PHP's native tmpfile().
     * Livewire file uploads call tmpfile() from a namespaced class, so we
     * must provide a global replacement that behaves similarly.
     */
    function tmpfile()
    {
        $directories = array_values(array_filter([
            function_exists('sys_get_temp_dir') ? sys_get_temp_dir() : null,
            dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'livewire-tmp',
        ], static fn ($directory) => is_string($directory) && $directory !== ''));

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }

            if (! is_dir($directory) || ! is_writable($directory)) {
                continue;
            }

            $path = @tempnam($directory, 'lw_');

            if ($path === false) {
                continue;
            }

            $handle = @fopen($path, 'c+b');

            if ($handle === false) {
                @unlink($path);

                continue;
            }

            if (function_exists('stream_set_close_delete')) {
                stream_set_close_delete($handle);
            } else {
                register_shutdown_function(static function () use ($path): void {
                    if (is_file($path)) {
                        @unlink($path);
                    }
                });
            }

            return $handle;
        }

        return false;
    }
}
