<?php
// JARVIS | NextCore: Digital Solutions
// /includes/header.php - v3.0 (Navbar compacta + dropdowns + RBAC atualizado)

require_once __DIR__ . '/../db.php';

$usuario_nome  = $_SESSION['usuario_nome']  ?? 'Usuário';
$usuario_cargo = $_SESSION['usuario_cargo'] ?? 'view';

// Helpers de permissão para clareza
function can_admin()                { return ($_SESSION['usuario_cargo'] ?? 'view') === 'admin'; }
function can_colab_nre()            { return in_array(($_SESSION['usuario_cargo'] ?? ''), ['admin','colaborador_nre'], true); }
function can_colab_escola()         { return in_array(($_SESSION['usuario_cargo'] ?? ''), ['admin','colaborador_escola'], true); }
function can_colab_nre_freq()       { return in_array(($_SESSION['usuario_cargo'] ?? ''), ['admin','colaborador_nre_freq'], true); }
function can_importar_csv()         { return can_admin(); } // apenas admin
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="auto">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Sistema de Métricas Educacionais | NextCore'; ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="assets/css/app.css" rel="stylesheet">
    <link href="assets/css/heatmap-styles.css" rel="stylesheet">

    <script>
    (function() {
        const storageKey = 'nextcore-theme';
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        let storageAvailable = true;
        try { localStorage.setItem('__t','1'); localStorage.removeItem('__t'); } catch(e){ storageAvailable = false; }

        const getStored = ()=> storageAvailable ? localStorage.getItem(storageKey) : null;
        const setStored = (v)=> { if(!storageAvailable) return; v==='auto'?localStorage.removeItem(storageKey):localStorage.setItem(storageKey,v); };
        const resolve = (v)=> (v==='light'||v==='dark')?v:(mediaQuery.matches?'dark':'light');

        const apply = (choice,{emit=true,persist=false}={})=>{
            const normalized = (choice==='light'||choice==='dark')?choice:'auto';
            const resolved = resolve(normalized);
            document.documentElement.setAttribute('data-bs-theme', resolved);
            document.documentElement.setAttribute('data-theme-choice', normalized);
            if(persist) setStored(normalized);
            if(emit){
                const detail={theme:resolved,choice:normalized};
                const ev = typeof CustomEvent==='function'? new CustomEvent('nextcore:theme-changed',{detail}) : (function(){const e=document.createEvent('CustomEvent');e.initCustomEvent('nextcore:theme-changed',false,false,detail);return e;})();
                window.dispatchEvent(ev);
            }
        };

        const initial = getStored() ?? 'auto';
        apply(initial,{emit:false});

        window.NextCoreTheme = {
            getChoice: ()=> document.documentElement.getAttribute('data-theme-choice') || 'auto',
            setChoice: (c)=> apply(c,{persist:true}),
            resetToSystem: ()=> apply('auto',{persist:true})
        };

        const onSysChange=()=>{ if (window.NextCoreTheme.getChoice()==='auto') apply('auto'); };
        mediaQuery.addEventListener ? mediaQuery.addEventListener('change', onSysChange)
                                    : mediaQuery.addListener && mediaQuery.addListener(onSysChange);
    })();
    </script>

    <style>
      /* Deixa a navbar mais arejada */
      .navbar .dropdown-menu { min-width: 240px; }
      .navbar .nav-link, .navbar .dropdown-item { white-space: nowrap; }
      .brand-subtitle { margin-top:-5px; }
      @media (max-width: 991.98px){
        .brand-subtitle { display:none; }
      }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm border-bottom border-secondary">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold text-primary" href="metricas.php">Métricas Educacionais</a>
    <small class="text-white-50 fst-italic ms-2 brand-subtitle">Painel Integrado de Indicadores – NextCore: Digital Solutions</small>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
      aria-controls="mainNav" aria-expanded="false" aria-label="Alternar navegação">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">

        <!-- Menu: Análises -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="menuAnalises" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fa-solid fa-chart-line me-1"></i> Análises
          </a>
          <ul class="dropdown-menu" aria-labelledby="menuAnalises">
            <li><a class="dropdown-item" href="metricas.php"><i class="fa-solid fa-gauge-high me-2"></i>Dashboard</a></li>
            <li><a class="dropdown-item" href="graficos_heatmap.php"><i class="fa-solid fa-square-h"></i> Heatmap</a></li>
            <li><a class="dropdown-item" href="escola_detalhes_reds.php"><i class="fa-solid fa-layer-group me-2"></i>RED’s</a></li>
          </ul>
        </li>

        <!-- Menu: Dados -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" id="menuDados" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fa-solid fa-database me-1"></i> Dados
          </a>
          <ul class="dropdown-menu" aria-labelledby="menuDados">
            <?php if (can_importar_csv()): ?>
              <li><a class="dropdown-item" href="upload_csv.php"><i class="fa-solid fa-file-csv me-2"></i>Importar (CSV)</a></li>
              <li><hr class="dropdown-divider"></li>
            <?php endif; ?>

            <?php if (can_colab_nre()): ?>
              <li><a class="dropdown-item" href="inserir_reds.php"><i class="fa-solid fa-square-plus me-2"></i>Inserir Dados RED’s</a></li>
            <?php endif; ?>

            <?php if (can_colab_escola()): ?>
              <li><a class="dropdown-item" href="frequencia_manual.php"><i class="fa-solid fa-user-check me-2"></i>Frequência Manual</a></li>
            <?php endif; ?>

            <?php if (can_colab_nre_freq()): ?>
              <li><a class="dropdown-item" href="revisao_frequencia.php"><i class="fa-solid fa-clipboard-check me-2"></i>Revisão de Frequência</a></li>
            <?php endif; ?>
          </ul>
        </li>

        <?php if (can_admin()): ?>
          <li class="nav-item">
            <a class="nav-link" href="usuarios.php"><i class="fa-solid fa-users-gear me-1"></i> Usuários</a>
          </li>
        <?php endif; ?>

      </ul>

      <!-- Tema + Usuário + Sair -->
      <div class="d-flex align-items-center flex-wrap gap-3">
        <div class="btn-group btn-group-sm" role="group" aria-label="Tema">
          <input type="radio" class="btn-check" name="theme-choice" id="themeChoiceAuto" data-theme-value="auto">
          <label class="btn btn-outline-light" for="themeChoiceAuto" title="Tema automático"><i class="fa-solid fa-circle-half-stroke"></i></label>

          <input type="radio" class="btn-check" name="theme-choice" id="themeChoiceLight" data-theme-value="light">
          <label class="btn btn-outline-light" for="themeChoiceLight" title="Tema claro"><i class="fa-solid fa-sun"></i></label>

          <input type="radio" class="btn-check" name="theme-choice" id="themeChoiceDark" data-theme-value="dark">
          <label class="btn btn-outline-light" for="themeChoiceDark" title="Tema escuro"><i class="fa-solid fa-moon"></i></label>
        </div>
        <span class="text-white-50 small d-none d-lg-inline" id="themeLabel">Tema</span>

        <div class="text-end text-white-50 small me-2">
          <div><strong><?= htmlspecialchars($usuario_nome); ?></strong></div>
          <div><?= strtoupper(str_replace('_',' ', $usuario_cargo)); ?></div>
        </div>

        <a href="logout.php" class="btn btn-outline-light btn-sm">Sair</a>
      </div>
    </div>
  </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const controls = document.querySelectorAll('input[data-theme-value]');
  const label = document.getElementById('themeLabel');
  const names = {light:'Claro',dark:'Escuro',auto:'Automático'};

  function applyState(choice){
    const normalized = names[choice]?choice:'auto';
    controls.forEach((el)=>{
      const active = el.dataset.themeValue===normalized;
      el.checked = active;
      const lab = el.nextElementSibling;
      if(lab){ lab.classList.toggle('active', active); lab.setAttribute('aria-pressed', active?'true':'false'); }
    });
    if(label) label.textContent = 'Tema: ' + names[normalized];
  }

  controls.forEach((el)=>{
    el.addEventListener('change', ()=>{
      if(!el.checked) return;
      window.NextCoreTheme && window.NextCoreTheme.setChoice(el.dataset.themeValue||'auto');
    });
  });

  window.addEventListener('nextcore:theme-changed', (e)=> applyState(e.detail?.choice || 'auto'));
  applyState((window.NextCoreTheme && window.NextCoreTheme.getChoice()) || 'auto');
});
</script>

<main class="container-fluid mt-4">
