<?php


namespace App\Traits;


use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

trait SeederHelper
{
    protected $modelClass;
    protected $recordsCount = 0;
    protected ConsoleOutput $output;
    protected ProgressBar $progressBar;

    public function __construct()
    {
    }

    public function init(string $modelClass, int $recordsCount)
    {
        $this->modelClass = $modelClass;
        $this->recordsCount = $recordsCount;

        $this->output = new ConsoleOutput();
        $this->output->setDecorated(true);
        $this->progressBar = new ProgressBar($this->output, $this->recordsCount);
        $this->progressBar->setMaxSteps($recordsCount);
    }

    public function createModel($data, $column = null)
    {
        $msg = "Creating " .ucwords(class_basename($this->modelClass)) .'';
        if($column){
            if(is_array($data[$column]))
            {
                $column = $data[$column][0] ?? "";
            }else{
                $column = $data[$column];
            }
            $msg = "Creating " .ucwords(class_basename($this->modelClass) . ": " . $column ?? "");
        }

        $this->progressBar->setMessage($msg);

        $record = ($this->modelClass)::create($data);

        $this->progressBar->advance();

        return $record;
    }

    public function seederFinished()
    {
        $this->progressBar->finish();
    }
}
