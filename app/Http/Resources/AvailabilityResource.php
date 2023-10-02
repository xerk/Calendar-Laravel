<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AvailabilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $days = [];
        $description = '';
        if($this->data) {
            foreach($this->data as $day) {
                if($day['enabled']) {
                    $days[] = $day['name'];
                }
            }
            if(count($days)) {
                $description = implode(', ', $days);
            }
        }


        return [
            'id' => $this->id,
            'value' => $this->id,
            'name' => $this->name,
            'label' => $this->name,
            'timezone' => $this->timezone,
            'description' => $description,
            'data' => $this->data,
        ];
    }
}
