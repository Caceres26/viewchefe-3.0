// assets/js/chart-defaults.js
(function () {
  if (typeof Chart === 'undefined') return;

  // Sem preenchimento/área nos gráficos de linha:
  Chart.defaults.elements.line.fill = false;
  Chart.defaults.datasets.line = Chart.defaults.datasets.line || {};
  Chart.defaults.datasets.line.fill = false;
  Chart.defaults.datasets.line.backgroundColor = 'transparent';

  // Se o plugin de "filler" estiver ativo, desabilita:
  Chart.defaults.plugins = Chart.defaults.plugins || {};
  Chart.defaults.plugins.filler = false;

  // Pontos e linhas mais limpos (opcional)
  Chart.defaults.elements.point.radius = 2;
  Chart.defaults.elements.point.hoverRadius = 3;
  Chart.defaults.elements.line.borderWidth = 2;
})();
