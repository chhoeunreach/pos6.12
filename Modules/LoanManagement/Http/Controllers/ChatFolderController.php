<?php

namespace Modules\LoanManagement\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\LoanManagement\Services\ChatFolderService;

class ChatFolderController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        return $this->ok('Folders loaded', ChatFolderService::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'customer_ids' => 'nullable|array',
            'customer_ids.*' => 'integer',
        ]);

        $folder = ChatFolderService::create(
            $validated['name'],
            $validated['customer_ids'] ?? []
        );

        return $this->ok('Folder created successfully', $folder);
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'customer_ids' => 'nullable|array',
            'customer_ids.*' => 'integer',
        ]);

        $folder = ChatFolderService::update($id, $validated);
        if (! $folder) {
            return $this->fail('Folder not found', 404);
        }

        return $this->ok('Folder updated successfully', $folder);
    }

    public function destroy(string $id)
    {
        $deleted = ChatFolderService::delete($id);
        if (! $deleted) {
            return $this->fail('Folder not found', 404);
        }

        return $this->ok('Folder deleted successfully', (object) []);
    }

    public function customerFolders(int $customerId)
    {
        $folderIds = ChatFolderService::getCustomerFolderIds($customerId);

        return $this->ok('Customer folders loaded', [
            'customer_id' => $customerId,
            'folder_ids' => $folderIds,
        ]);
    }

    public function updateCustomerFolders(Request $request, int $customerId)
    {
        $validated = $request->validate([
            'folder_ids' => 'nullable|array',
            'folder_ids.*' => 'string',
        ]);

        ChatFolderService::setCustomerFolders($customerId, $validated['folder_ids'] ?? []);

        return $this->ok('Customer folders updated successfully', [
            'customer_id' => $customerId,
            'folder_ids' => ChatFolderService::getCustomerFolderIds($customerId),
        ]);
    }
}
