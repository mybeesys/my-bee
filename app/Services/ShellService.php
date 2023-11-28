<?php


namespace App\Services;


use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ShellService
{

    //in linux use: which

//C:\Users\Monzer>where notepad
//C:\Windows\System32\notepad.exe
//C:\Windows\notepad.exe
//C:\Users\Monzer\AppData\Local\Microsoft\WindowsApps\notepad.exe

    public const COMMAND_MYSQLDUMP = "mysqldump";

    public static function instance(): ShellService
    {
        return new self();
    }

    public function available(string $command): bool
    {
        $whereIsCommand = (PHP_OS == 'WINNT') ? 'where' : 'which';

        $process = proc_open(
            "$whereIsCommand $command",
            array(
                0 => array("pipe", "r"), //STDIN
                1 => array("pipe", "w"), //STDOUT
                2 => array("pipe", "w"), //STDERR
            ),
            $pipes
        );

        if ($process !== false) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            return $stdout != '';
        }

        return false;
    }
}
