<?php

namespace App\Controllers;

use App\Models\Notification;
use App\controllers\Controller;

class NotificationController extends Controller
{
    public function index()
    {
        if (!$this->requireAuth()) return;

        $notifModel = new Notification();
        $notifications = $notifModel->getForUser($_SESSION['user_id']);
        $this->json($notifications);
    }

    public function unreadCount()
    {
        if (!$this->requireAuth()) return;

        $notifModel = new Notification();
        $count = $notifModel->getUnreadCount($_SESSION['user_id']);
        $this->json(['count' => $count]);
    }

    public function markRead($id)
    {
        if (!$this->requireAuth()) return;

        if (is_array($id)) $id = $id['id'] ?? null;
        $id = (int)$id;

        if (!$id) {
            $this->status(400)->json(['error' => 'ID notification manquant']);
            return;
        }

        $notifModel = new Notification();
        $notifModel->markAsRead($id, $_SESSION['user_id']);
        $this->json(['success' => true]);
    }

    public function markAllRead()
    {
        if (!$this->requireAuth()) return;

        $notifModel = new Notification();
        $notifModel->markAllAsRead($_SESSION['user_id']);
        $this->json(['success' => true, 'message' => 'Toutes les notifications marquées comme lues']);
    }

    public function delete($id)
    {
        if (!$this->requireAuth()) return;

        if (is_array($id)) $id = $id['id'] ?? null;
        $id = (int)$id;

        if (!$id) {
            $this->status(400)->json(['error' => 'ID notification manquant']);
            return;
        }

        $notifModel = new Notification();
        $notifModel->delete($id, $_SESSION['user_id']);
        $this->json(['success' => true]);
    }
}
