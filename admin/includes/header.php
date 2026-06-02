<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
auth_required();
$flash    = get_flash();
$pageFile = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? 'Admin') ?> – Proimprove Admin</title>
<link rel="stylesheet" href="/admin/css/admin.css">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="sidebar-logo">
      <span class="logo-pro">Pro</span><span class="logo-improve">improve</span>
      <small>Admin</small>
    </div>
    <nav class="sidebar-nav">
      <a href="/admin/dashboard.php" class="<?= $pageFile==='dashboard.php'?'active':''?>">
        <span>📊</span> Dashboard
      </a>
      <div class="nav-group">Inhalt</div>
      <a href="/admin/sections.php" class="<?= $pageFile==='sections.php'?'active':''?>">
        <span>✏️</span> Seiteninhalt
      </a>
      <a href="/admin/services.php" class="<?= $pageFile==='services.php'?'active':''?>">
        <span>⚙️</span> Leistungen
      </a>
      <a href="/admin/testimonials.php" class="<?= $pageFile==='testimonials.php'?'active':''?>">
        <span>💬</span> Referenzen
      </a>
      <div class="nav-group">Daten</div>
      <a href="/admin/contacts.php" class="<?= $pageFile==='contacts.php'?'active':''?>">
        <span>📩</span> Kontaktanfragen
        <?php $unread = db_get('SELECT COUNT(*) c FROM contact_submissions WHERE is_read=0'); ?>
        <?php if ($unread && $unread['c'] > 0): ?>
          <span class="badge"><?= (int)$unread['c'] ?></span>
        <?php endif ?>
      </a>
      <a href="/admin/media.php" class="<?= $pageFile==='media.php'?'active':''?>">
        <span>🖼️</span> Medien
      </a>
      <div class="nav-group">System</div>
      <a href="/admin/settings.php" class="<?= $pageFile==='settings.php'?'active':''?>">
        <span>⚙️</span> Einstellungen
      </a>
      <a href="/" target="_blank"><span>🌐</span> Website ansehen</a>
    </nav>
    <div class="sidebar-footer">
      <span><?= h(current_user()) ?></span>
      <a href="/admin/logout.php">Abmelden</a>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <h1 class="page-title"><?= h($pageTitle ?? 'Dashboard') ?></h1>
    </div>
    <div class="content">
      <?php if ($flash): ?>
        <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
      <?php endif ?>
