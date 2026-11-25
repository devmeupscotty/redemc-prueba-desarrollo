<?php

use App\Constants;

/** @var string $clientConfig */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Registro de personas con validación y listado paginado.">
    <title>Registro de personas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/css/bootstrap.min.css"
          integrity="sha384-yEPZ7jP9S0QKiUQ0m3jjsO4ProIW7wvR9gXkI7JOGa2XVsw53Yke2bsdkBbjmmiX" crossorigin="anonymous">
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>
<header class="app-header">
    <div class="container">
        <h1 class="app-title">Registro de personas</h1>
        <p class="app-subtitle">Ingresá tus datos y se agregarán al instante al listado.</p>
    </div>
</header>

<main class="container app-main">
    <div class="row g-4">
        <section class="col-lg-4" aria-labelledby="form-title">
            <div class="panel panel--sticky">
                <h2 class="panel-title" id="form-title">Ingreso de datos</h2>
                <p class="required-legend"><span class="required-mark" aria-hidden="true">*</span> Campos obligatorios</p>

                <form id="person-form" novalidate>
                    <div class="form-floating field">
                        <input type="text" class="form-control" id="name" name="name" placeholder="Nombre"
                               maxlength="<?= Constants::MAX_LENGTH[Constants::FIELD_NAME] ?>" autocomplete="name" required aria-required="true" aria-describedby="name-error">
                        <label for="name">Nombre <span class="required-mark" aria-hidden="true">*</span></label>
                        <div class="invalid-feedback" id="name-error"></div>
                    </div>

                    <div class="form-floating field">
                        <input type="email" class="form-control" id="email" name="email" placeholder="Email"
                               maxlength="<?= Constants::MAX_LENGTH[Constants::FIELD_EMAIL] ?>" autocomplete="email" required aria-required="true" aria-describedby="email-error">
                        <label for="email">Email <span class="required-mark" aria-hidden="true">*</span></label>
                        <div class="invalid-feedback" id="email-error"></div>
                    </div>

                    <div class="form-floating field">
                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="Teléfono"
                               inputmode="numeric" autocomplete="tel-national" aria-describedby="phone-hint phone-error">
                        <label for="phone">Teléfono <span class="optional-mark">(opcional)</span></label>
                        <div class="form-text" id="phone-hint">Celular uruguayo de 9 dígitos, ej: 097680102</div>
                        <div class="invalid-feedback" id="phone-error"></div>
                    </div>

                    <div class="alert alert-danger form-alert d-none" id="form-alert" role="alert"></div>

                    <button type="submit" class="btn btn-primary btn-submit w-100" id="submit-button">
                        <span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span>
                        <span class="button-label">Guardar</span>
                    </button>
                </form>
            </div>
        </section>

        <section class="col-lg-8" aria-labelledby="list-title">
            <div class="panel">
                <div class="list-header">
                    <h2 class="panel-title" id="list-title">Listado de datos</h2>
                    <span class="total-badge" id="total-badge" aria-live="polite"></span>
                </div>

                <div class="person-list">
                    <div class="person-row person-row--head" aria-hidden="true">
                        <div>Nombre</div>
                        <div>Email</div>
                        <div>Teléfono</div>
                        <div>Fecha de ingreso</div>
                    </div>
                    <div id="person-rows" role="list" aria-live="polite" aria-busy="true"></div>
                </div>

                <nav class="list-pagination" aria-label="Paginación del listado">
                    <ul class="pagination justify-content-center mb-0" id="pagination"></ul>
                </nav>
            </div>
        </section>
    </div>
</main>

<template id="person-row-template">
    <div class="person-row" role="listitem">
        <div class="person-cell" data-label="Nombre"><span class="person-value" data-field="name"></span></div>
        <div class="person-cell" data-label="Email"><span class="person-value" data-field="email"></span></div>
        <div class="person-cell" data-label="Teléfono"><span class="person-value" data-field="phone"></span></div>
        <div class="person-cell" data-label="Fecha de ingreso"><span class="person-value" data-field="createdAt"></span></div>
    </div>
</template>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div class="toast align-items-center border-0 toast-success" id="success-toast" role="status" aria-live="polite" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">Registro guardado correctamente.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
        </div>
    </div>
</div>

<script type="application/json" id="app-config"><?= $clientConfig ?></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@4.1.0/dist/jquery.min.js"
        integrity="sha384-UGSi5euAzE1p4sK41+yKs3vtdPcidmaW6fY2NUITZkjIPyMVUUYB/BV4A5Oa8uu2" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.9/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-WyOxWvTyr1QaMPfiPJSv76iReVthorl5uWlFtZPyK0IEB0hGzo9y5Bp9mYanr8Bu" crossorigin="anonymous"></script>
<script type="module" src="/js/app.js"></script>
</body>
</html>
