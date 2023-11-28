<?php


namespace App\Services;


use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

class FilamentNotificationService
{


    protected $status = "success", $title, $body = null, $persist = false, $db_recipients = null, $broadcast_recipients = null;


    public function __construct()
    {
        $this->db_recipients = collect();
        $this->broadcast_recipients = collect();
    }

    public function status($status): self
    {
        $this->status = $status;
        return $this;
    }

    public function title($title): self
    {
        $this->title = $title;
        return $this;
    }

    public function body($body): self
    {
        $this->body = $body;
        return $this;
    }

    public function persist(bool $persist = false): self
    {
        $this->persist = $persist;
        return $this;
    }

    public function recipients(Collection $db_recipients = null): self
    {
        $this->db_recipients = $db_recipients ?? collect();
        return $this;
    }

    public function broadcast(Collection $recipients = null): self
    {
        $this->broadcast_recipients = $recipients ?? collect();
        return $this;
    }

//    public function toSuperAdmin(): self
//    {
//        $this->db_recipients = User::where('id', 1)->get();
//        return $this;
//    }
//
//    public function toSuperAdminAndSupervisors(): self
//    {
//        $this->db_recipients = User::superAdminOrSuperVisor()->get();
//        return $this;
//    }
//
//    public function toSupervisors(): self
//    {
//        $this->db_recipients = User::superVisor()->get();
//        return $this;
//    }

    public function toRoles(array $roles_names): self
    {
        $this->db_recipients = User::whereHas('roles', function ($q) use ($roles_names) {
            return $q->whereIn('name', $roles_names);
        })->get();
        return $this;
    }

    public function send()
    {
        $filamentNotification = Notification::make()
            ->status($this->status)
            ->title($this->title)
            ->body($this->body);

        if ($this->persist)
            $filamentNotification->persistent();

        if ($this->db_recipients and $this->db_recipients->isNotEmpty())
            $filamentNotification->sendToDatabase($this->db_recipients);

        if ($this->broadcast_recipients and $this->broadcast_recipients->isNotEmpty())
            $filamentNotification->broadcast($this->broadcast_recipients);

        $filamentNotification->send();

        $this->reset();
    }

    public function displayException(\Throwable $exception): void
    {
        if($exception instanceof QueryException and ($exception->errorInfo[1] ?? null) == 1451)
        {
            $this->status = "warning";
            $this->title = "Delete failed";
            $this->body = "Record in-use and cannot be deleted.";
        } else {
            $this->status = "danger";
            $this->title = "Something went wrong";
            $this->body = $exception->getMessage();
        }

        $this->persist = true;
        $this->send();

        $this->reset();
    }

    public function created()
    {
        $this->status = "success";
        $this->title = "Created";
        $this->send();
    }


    public function deleted()
    {
        $this->status = "success";
        $this->title = "Deleted";
        $this->send();
    }

    public function saved()
    {
        $this->status = "success";
        $this->title = "Saved";
        $this->send();
    }

    public function sendSuccess($title, $body = null)
    {
        $this->status = "success";
        $this->title = $title;
        $this->body = $body;
        $this->send();
    }

    public function sendDanger($title, $body = null)
    {
        $this->status = "danger";
        $this->title = $title;
        $this->body = $body;
        $this->send();
    }

    public function sendWarning($title, $body = null)
    {
        $this->status = "warning";
        $this->title = $title;
        $this->body = $body;
        $this->send();
    }


    public function sendRecordInUse($status = "warning", $body = null): void
    {
        $this->status = "warning";
        $this->title = __('fields.record_in_use_alert');
        $this->body = $body;
        $this->send();
    }

    public function reset(): self
    {
        $this->status = "success";
        $this->title = null;
        $this->body = null;
        $this->persist = false;
        $this->db_recipients = collect();

        return $this;
    }
}
