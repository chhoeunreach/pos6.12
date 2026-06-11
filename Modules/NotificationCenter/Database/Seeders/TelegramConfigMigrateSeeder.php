<?php

namespace Modules\NotificationCenter\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\NotificationCenter\Entities\NotificationGroup;
use Modules\NotificationCenter\Entities\NotificationTemplate;

class TelegramConfigMigrateSeeder extends Seeder
{
    protected function resolveBusinessLocationId(string $locationName): ?int
    {
        try {
            $location = \DB::table('business_locations')
                ->where('name', $locationName)
                ->orWhere('name', 'like', '%'.$locationName.'%')
                ->orWhere('landmark', 'like', '%'.$locationName.'%')
                ->first(['id']);
            return $location->id ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function run(): void
    {
        $config = config('telegram');

        if (empty($config['stock_transfer']['from_location_channels'])) {
            if ($this->command) {
                $this->command->warn('No from_location_channels found in config/telegram.php');
            }
            return;
        }

        $fromChannels = $config['stock_transfer']['from_location_channels'] ?? [];
        $toChannels = $config['stock_transfer']['to_location_channels'] ?? [];

        foreach ($fromChannels as $locationName => $chatId) {
            $locationId = $this->resolveBusinessLocationId($locationName);
            $chatIds = is_array($chatId) ? $chatId : explode(',', $chatId);
            foreach ($chatIds as $singleId) {
                $singleId = trim($singleId);
                if (empty($singleId)) continue;

                $group = NotificationGroup::firstOrNew(
                    ['chat_id' => $singleId, 'module_type' => 'stock_transfer', 'location_name' => $locationName]
                );
                $group->name = 'From: '.$locationName;
                $group->location_id = $locationId;
                $group->send_text = true;
                $group->send_pdf = true;
                $group->active = true;
                $group->direction = 'from';
                $group->save();
            }
        }

        foreach ($toChannels as $locationName => $chatId) {
            $locationId = $this->resolveBusinessLocationId($locationName);
            $chatIds = is_array($chatId) ? $chatId : explode(',', $chatId);
            foreach ($chatIds as $singleId) {
                $singleId = trim($singleId);
                if (empty($singleId)) continue;

                $group = NotificationGroup::firstOrNew(
                    ['chat_id' => $singleId, 'module_type' => 'stock_transfer', 'location_name' => $locationName]
                );
                $group->name = 'To: '.$locationName;
                $group->location_id = $locationId;
                $group->send_text = true;
                $group->send_pdf = true;
                $group->active = true;
                $group->direction = 'to';
                $group->save();
            }
        }

        NotificationTemplate::firstOrCreate(
            ['module_type' => 'stock_transfer', 'title' => 'Stock Transfer Default'],
            [
                'message_template' => "Stock Transfer {{ref_no}}\nFrom: {{from_location}}\nTo: {{to_location}}\nDate: {{date}}\nStatus: {{status}}\nTotal Qty: {{total_qty}}\nCreated by: {{user}}",
                'pdf_template_view' => 'notificationcenter::pdf.stock_transfer',
                'active' => true,
            ]
        );

        if ($this->command) {
            $this->command->info('Migrated '.count($fromChannels).' from-channels and '.count($toChannels).' to-channels into notification_groups.');
        }
    }
}
