<?php
// Incluir en el <head> de cada página protegida.
// Si PHP tiene sesión activa pero sessionStorage no tiene la marca (pestaña nueva
// o el usuario cerró la pestaña anterior), se fuerza logout.
if (!sesionActiva()) return;
?>
<script>
(function () {
    if (!sessionStorage.getItem('huella_tab')) {
        location.replace('/huella/logout.php?back=' + encodeURIComponent(location.pathname + location.search));
    }
})();
</script>
