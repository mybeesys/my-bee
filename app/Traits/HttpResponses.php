<?php


namespace App\Traits;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait HttpResponses
{
    private $statusCode = 200;
    private $statusText = "OK";
    private $message = "";
    private $data = [];
    private $errors = [];
    private $filters = [];
    private $debug = [];
    private $request = null;

    private $STATUS_CODE_SUCCESS = 200;
    private $STATUS_CODE_CREATED = 201;
    private $STATUS_CODE_UNAUTHORIZED = 401;
    private $STATUS_CODE_FORBIDDEN = 403;
    private $STATUS_CODE_NOT_FOUND = 404;
    private $STATUS_CODE_NOT_ALLOWED = 405;
    private $STATUS_CODE_BAD_REQUEST = 400;
    private $STATUS_CODE_VALIDATION_ERROR = 422;
    private $STATUS_CODE_TOO_MANY_ATTEMPTS = 429;
    private $STATUS_CODE_SERVER_ERROR = 500;


    public static $STATUS_CODE_WALLET_BALANCE_EMPTY = 100;
    public static $STATUS_CODE_WALLET_BALANCE_INSUFFICIENT_FUNDS = 101;
    public static $STATUS_CODE_WALLET_BALANCE_TRANSACTION_FAILED = 102;
    public static $STATUS_CODE_ACCOUNT_DEACTIVATED = 103;
    public static $STATUS_CODE_ACCOUNT_LINKED_TO_ANOTHER_DEVICE_CHANGE_DEVICE_ALERT = 104;
    public static $STATUS_CODE_ACCOUNT_LINKED_TO_ANOTHER_DEVICE = 105;

    public function statusCode($statusCode)
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function setStatusText()
    {
        switch ($this->statusCode) {
            case 200:
                $this->statusText = 'Success';
                break;
            case 201:
                $this->statusText = 'Created';
                break;
            case 204:
                $this->statusText = 'No Content';
                break;
            case 400:
                $this->statusText = 'Bad Request';
                break;
            case 401:
                $this->statusText = 'Unauthorized';
                break;
            case 402:
                $this->statusText = 'Payment Required';
                break;
            case 403:
                $this->statusText = 'Forbidden';
                break;
            case 404:
                $this->statusText = 'Not Found';
                break;
            case 405:
                $this->statusText = 'Method not allowed';
                break;
            case 422:
                $this->statusText = 'Unprocessable Content';
                break;
            case 429:
                $this->statusText = 'Too Many Attempts.';
                break;
            case 500:
                $this->statusText = 'Internal Server Error';
                break;
            case 100:
                $this->statusText = 'Wallet Balance is empty';
                break;
            case 101:
                $this->statusText = 'Wallet Insufficient funds';
                break;
            case 102:
                $this->statusText = 'Wallet Transaction failed';
                break;
            case 103:
                $this->statusText = 'Account Deactivated';
                break;
        }
        return $this;
    }

    public function request($request)
    {
        $this->request = $request;
        return $this;
    }

    public function getFilters():array
    {
        return array_merge($this->filters ?? [], $this->request ? $this->request->validated() : []);
    }

    public function message($message)
    {
        $this->message = $message;
        return $this;
    }

    public function filters($filters = [])
    {
        $this->filters = $filters;
        return $this;
    }

    public function debug($debug = [])
    {
        $this->debug = $debug;
        return $this;
    }

    public function data($data)
    {
        $this->data = $data;
        return $this;
    }

    public function errors($errors)
    {
        $this->errors = $errors;
        return $this;
    }

    public function ok()
    {
        $this->statusCode = $this->STATUS_CODE_SUCCESS;
        $this->setStatusText();
        return $this;
    }

    public function created($data)
    {
        $this->statusCode = $this->STATUS_CODE_CREATED;
        $this->setStatusText();
        $this->data = $data;
        return $this;
    }

    public function errorNotFound($msg = "")
    {
        $this->message = $msg == "" ? $this->message : $msg;
        $this->statusCode = $this->STATUS_CODE_NOT_FOUND;
        $this->setStatusText();
        return $this;
    }

    public function errorUnauthorized()
    {
        $this->statusCode = $this->STATUS_CODE_UNAUTHORIZED;
        $this->setStatusText();
        return $this;
    }

    public function errorForbidden()
    {
        $this->statusCode = $this->STATUS_CODE_FORBIDDEN;
        $this->setStatusText();
        return $this;
    }

    public function errorTooManyAttempts()
    {
        $this->statusCode = $this->STATUS_CODE_TOO_MANY_ATTEMPTS;
        $this->setStatusText();
        return $this;
    }

    public function errorBadRequest()
    {
        $this->statusCode = $this->STATUS_CODE_BAD_REQUEST;
        $this->setStatusText();
        return $this;
    }

    public function error($exception): self
    {
        switch ($exception) {
            case $exception instanceof AuthenticationException:
            {
                $this->errorUnauthorized();
                $this->message($exception->getMessage());
                break;
            }
            case $exception instanceof ValidationException:
            {
                $this->statusCode = $this->STATUS_CODE_VALIDATION_ERROR;
                $this->setStatusText();
                $this->errors($exception->errors());
                $this->message = $exception->getMessage();
                break;
            }
            case $exception instanceof ModelNotFoundException:
            {
                $this->errorNotFound("Record not found.");
                break;
            }
            case $exception instanceof NotFoundHttpException:
            {
                $this->statusCode = $this->STATUS_CODE_NOT_FOUND;
                $this->setStatusText();
                $this->message = $exception->getMessage();
                break;
            }
            case $exception instanceof MethodNotAllowedHttpException:
            {
                $this->statusCode = $this->STATUS_CODE_NOT_ALLOWED;
                $this->setStatusText();
                $this->message = $exception->getMessage();
                break;
            }

            case $exception instanceof AuthorizationException:
            {
                $this->errorForbidden();
                break;
            }
            case $exception instanceof ThrottleRequestsException:
            {
                $this->errorTooManyAttempts();
                break;
            }
            default :
            {
                $this->statusCode = method_exists($exception, "getStatusCode") ? $exception->getStatusCode() : 500;
                $this->statusText = $exception->getMessage();
                $this->message = $exception->getMessage();
            }
        }
        return $this;
    }

    public function respond(): \Illuminate\Http\JsonResponse
    {
        $this->setStatusText();

        $data = response()->json([
            'statusCode' => $this->statusCode,
            'statusText' => $this->statusText,
            'message' => $this->message,
            'data' => $this->data,
            'filters' => $this->getFilters(),
            'errors' => $this->errors,
            'debug' => $this->debug,
            'locale' => config('app.locale'),
        ], $this->statusCode);

        $this->reset();

        return $data;
    }

    public function paginate($data, Collection $additionalData = null): \Illuminate\Http\JsonResponse
    {
        $perPage = request("per_page") ?? 10;
        $currentPage = request("page") ?? 1;

        $paginator = new LengthAwarePaginator(
            $data->forPage($currentPage, $perPage)->values(),
            $data->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);


        if($additionalData)
        {
            $data = $additionalData->merge($paginator);
        }else{
            $data = $paginator;
        }

        $data = response()->json([
            'statusCode' => $this->statusCode,
            'statusText' => $this->statusText,
            'message' => $this->message,
            'data' => $data,
            'errors' => $this->errors,
            'filters' => $this->getFilters(),
            'debug' => $this->debug,
            'locale' => config('app.locale'),
        ], $this->statusCode);

        $this->reset();
        return $data;
    }

    function reset(): void
    {
        $this->data = "";
        $this->message = "";
        $this->errors = "";
        $this->statusCode = 200;
        $this->statusText = "";
    }

    public function responder($message, $code, $data = [], $errors = [], $additional_filters = []): self
    {
        $this->statusCode = $code;
        $this->setStatusText();
        $this->message = $message;
        $this->data = $data;
        $this->errors = $errors;
        $this->filters = array_merge($this->getFilters(), $additional_filters);
        return $this;
    }
}
