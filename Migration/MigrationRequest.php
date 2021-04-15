<?php


namespace App\Services\Migration;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Http\FormRequest;

class MigrationRequest extends FormRequest
{
    use AuthorizesRequests;

    protected $allowed_databases = [
        "OCR",
        "addresses",
        "sharedStorage",
        "customers",
        "proactive_config"
    ];

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "databases" => "required|array|in:" . implode(",", $this->allowed_databases)
        ];
    }

    /**
     * @return array
     */
    public function messages()
    {
        return [
            "databases.required" => "The `databases` field is required.",
            "databases.array" => "Value of the `databases` should be an array",
            "databases.in" => "Only the values " . implode(",", $this->allowed_databases) . " are allowed"
        ];
    }
}
