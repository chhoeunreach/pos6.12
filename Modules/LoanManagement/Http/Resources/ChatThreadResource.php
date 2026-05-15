<?php

namespace Modules\LoanManagement\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChatThreadResource extends JsonResource
{
    public function toArray($request): array
    {
        $messageCount = (int) $this->messages()->count();
        $viewerType = $request->attributes->get('loan_chat_viewer_type');
        if ($viewerType) {
            $service = app(\Modules\LoanManagement\Services\LoanChatService::class);
            $data = $service->formatMessengerThread($this->resource, $viewerType);
            $data['message_count'] = $messageCount;
            $data['can_delete'] = $messageCount === 0;
            if ($this->relationLoaded('messages')) {
                $data['messages'] = ChatMessageResource::collection($this->messages)->resolve($request);
            }

            return $data;
        }

        $participants = [];
        if ($this->relationLoaded('participants')) {
            foreach ($this->participants as $p) {
                $participants[] = [
                    'participant_type' => (string) ($p->participant_type ?? ''),
                    'participant_id' => (int) ($p->participant_id ?? 0),
                    'participant_name_snapshot' => (string) ($p->participant_name_snapshot ?? ''),
                    'last_read_at' => $p->last_read_at ? $p->last_read_at->toISOString() : null,
                    'joined_at' => $p->joined_at ? $p->joined_at->toISOString() : null,
                    'left_at' => $p->left_at ? $p->left_at->toISOString() : null,
                ];
            }
        }

        return [
            'id' => (int) $this->id,
            'thread_number' => (string) ($this->thread_number ?? ''),
            'customer_id' => $this->customer_id === null ? null : (int) $this->customer_id,
            'staff_id' => $this->staff_id === null ? null : (int) $this->staff_id,
            'loan_id' => $this->loan_id === null ? null : (int) $this->loan_id,
            'subject' => $this->subject === null ? null : (string) $this->subject,
            'type' => (string) ($this->type ?? ''),
            'status' => (string) ($this->status ?? ''),
            'priority' => (string) ($this->priority ?? ''),
            'avatar_url' => (string) ($this->avatar_url ?? ''),
            'is_online' => false,
            'is_pinned' => (bool) ($this->is_pinned ?? false),
            'is_muted' => (bool) ($this->is_muted ?? false),
            'typing' => false,
            'last_message' => $this->last_message === null ? null : (string) $this->last_message,
            'last_message_type' => $this->last_message_type === null ? null : (string) $this->last_message_type,
            'last_message_at' => $this->last_message_at ? $this->last_message_at->toISOString() : null,
            'unread_customer_count' => (int) ($this->unread_customer_count ?? 0),
            'unread_staff_count' => (int) ($this->unread_staff_count ?? 0),
            'closed_at' => $this->closed_at ? $this->closed_at->toISOString() : null,
            'closed_by' => $this->closed_by === null ? null : (int) $this->closed_by,
            'created_by_type' => (string) ($this->created_by_type ?? ''),
            'created_by_id' => (int) ($this->created_by_id ?? 0),
            'message_count' => $messageCount,
            'can_delete' => $messageCount === 0,
            'participants' => $participants,
            'messages' => $this->relationLoaded('messages') ? ChatMessageResource::collection($this->messages)->resolve() : [],
            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toISOString() : null,
        ];
    }
}
