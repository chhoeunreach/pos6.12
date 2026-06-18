<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'surname' => $this->surname,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->user_full_name,
            'username' => $this->username,
            'email' => $this->email,
            'language' => $this->language,
            'business_id' => $this->business_id,
            'image_url' => $this->image_url,
            'role_name' => $this->role_name,
        ];
    }
}
