<?php
/**
 * @var $this \CodeIgniter\View\View
 */
?>

<?php $this->extend('layouts/base.layout.php') ?>

<?php $this->section('header'); ?>
   <?= $this->renderSection('header'); ?>

   <!-- add runtime changes to the head element here -->
<?php $this->endSection(); ?>

<?php $this->section('content'); ?>
   <?= $this->renderSection('content'); ?>

   <!-- Customize this content section as you would like -->
<?php $this->endSection(); ?>

<?php $this->section('footer'); ?>
   <?= $this->renderSection('footer'); ?>

   <!-- add runtime changes to the footer section here -->
<?php $this->endSection(); ?>