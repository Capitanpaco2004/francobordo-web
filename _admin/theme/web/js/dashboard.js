/**
 * Dashboard Ecommerce - Preciosadictos
 * Drag header = reorder | Drag right edge = resize | X = hide | Config panel = add back
 */

var DashBoard = {
    charts: {},
    configUrl: window.location.pathname,
    editMode: false,
    dragEl: null,
    placeholder: null,
    resizing: null,
    resizeStartX: 0,
    resizeStartSize: 0,
    refreshTimer: null,
    refreshMinutes: 5, // default

    sizes: [4, 6, 8, 12],
    sizeLabels: { 4: '1/3', 6: '1/2', 8: '2/3', 12: 'Full' },

    kpiSizes: [2, 3, 4, 6],
    kpiSizeLabels: { 2: '1/6', 3: '1/4', 4: '1/3', 6: '1/2' },

    init: function() {
        var self = this;
        self.injectCellControls();
        self.injectKpiControls();
        self.createPlaceholder();
        self.initSalesChart();
        self.initOrdersStatusChart();
        self.initTabs();
        self.initConfigPanel();
        self.initEditMode();
        self.initGridDragDrop();
        self.initEdgeResize();
        self.initHideButtons();
        self.initConfigDragDrop();
        self.initConfigResize();
        self.initKpiDragDrop();
        self.initKpiHide();
        self.initKpiEdgeResize();
        self.initConfigKpiResize();
        self.initConfigKpis();
        self.initAutoRefresh();
        self.animateNumbers();
        // After animations complete, disable them so drag/drop doesn't retrigger
        setTimeout(function() {
            var kpiGrid = document.getElementById('dash-kpis-grid');
            if (kpiGrid) kpiGrid.classList.add('dash-kpis-ready');
        }, 800);
    },

    // ==========================================
    // Inject resize handle + hide button into each cell
    // ==========================================
    injectCellControls: function() {
        document.querySelectorAll('#dash-sortable-grid .dash-cell').forEach(function(cell) {
            // Resize handle (right edge)
            if (!cell.querySelector('.dash-resize-handle')) {
                var handle = document.createElement('div');
                handle.className = 'dash-resize-handle';
                handle.title = 'Arrastra para redimensionar';
                cell.appendChild(handle);
            }
            // Hide button (X)
            if (!cell.querySelector('.dash-hide-btn')) {
                var btn = document.createElement('button');
                btn.className = 'dash-hide-btn';
                btn.title = 'Ocultar widget';
                btn.innerHTML = '<i class="fa fa-times"></i>';
                cell.appendChild(btn);
            }
            // Size indicator (shown during resize)
            if (!cell.querySelector('.dash-size-indicator')) {
                var ind = document.createElement('div');
                ind.className = 'dash-size-indicator';
                cell.appendChild(ind);
            }
        });
    },

    // Inject resize handle + size indicator into each KPI card
    injectKpiControls: function() {
        document.querySelectorAll('#dash-kpis-grid .dash-kpi').forEach(function(kpi) {
            if (!kpi.querySelector('.dash-kpi-resize-handle')) {
                var handle = document.createElement('div');
                handle.className = 'dash-kpi-resize-handle';
                handle.title = 'Arrastra para redimensionar';
                kpi.appendChild(handle);
            }
            if (!kpi.querySelector('.dash-kpi-size-indicator')) {
                var ind = document.createElement('div');
                ind.className = 'dash-kpi-size-indicator';
                kpi.appendChild(ind);
            }
        });
    },

    createPlaceholder: function() {
        this.placeholder = document.createElement('div');
        this.placeholder.className = 'dash-cell dash-drop-placeholder';
        this.placeholder.innerHTML = '<div class="dash-placeholder-inner"><i class="fa fa-arrows-alt"></i> Soltar aqui</div>';
    },

    // ==========================================
    // Edge Resize: drag right border to change column span
    // ==========================================
    initEdgeResize: function() {
        var self = this;
        var grid = document.getElementById('dash-sortable-grid');
        if (!grid) return;

        // Get grid pixel width for calculating thresholds
        function getGridWidth() {
            return grid.getBoundingClientRect().width;
        }

        // Calculate size from mouse delta
        function calcSize(startSize, deltaX, gridWidth) {
            var colWidth = gridWidth / 12;
            var deltaCols = Math.round(deltaX / colWidth);
            var newSize = startSize + deltaCols;
            // Snap to nearest valid size
            var best = self.sizes[0];
            var bestDist = Math.abs(newSize - best);
            for (var i = 1; i < self.sizes.length; i++) {
                var d = Math.abs(newSize - self.sizes[i]);
                if (d < bestDist) { best = self.sizes[i]; bestDist = d; }
            }
            return best;
        }

        document.addEventListener('mousedown', function(e) {
            if (!self.editMode) return;
            var handle = e.target.closest('.dash-resize-handle');
            if (!handle) return;
            e.preventDefault();
            e.stopPropagation();

            var cell = handle.closest('.dash-cell');
            if (!cell) return;

            self.resizing = cell;
            self.resizeStartX = e.clientX;
            self.resizeStartSize = parseInt(cell.dataset.size) || 12;

            cell.classList.add('dash-resizing');
            handle.classList.add('active');

            // Show initial indicator
            var ind = cell.querySelector('.dash-size-indicator');
            if (ind) ind.textContent = self.sizeLabels[self.resizeStartSize];

            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
        });

        document.addEventListener('mousemove', function(e) {
            if (!self.resizing) return;
            e.preventDefault();

            var deltaX = e.clientX - self.resizeStartX;
            var newSize = calcSize(self.resizeStartSize, deltaX, getGridWidth());

            // Apply new size visually
            var cell = self.resizing;
            var currentSize = parseInt(cell.dataset.size) || 12;
            if (newSize !== currentSize) {
                cell.className = cell.className.replace(/dash-w-\d+/g, '');
                cell.classList.add('dash-w-' + newSize);
                cell.classList.add('dash-resizing');
                cell.dataset.size = newSize;
            }

            // Update indicator
            var ind = cell.querySelector('.dash-size-indicator');
            if (ind) ind.textContent = self.sizeLabels[newSize];
        });

        document.addEventListener('mouseup', function(e) {
            if (!self.resizing) return;

            var cell = self.resizing;
            cell.classList.remove('dash-resizing');
            var handle = cell.querySelector('.dash-resize-handle');
            if (handle) handle.classList.remove('active');

            document.body.style.cursor = '';
            document.body.style.userSelect = '';

            // Sync config panel
            var wid = cell.dataset.widget;
            var size = parseInt(cell.dataset.size) || 12;
            var cfgItem = document.querySelector('.dash-config-item[data-widget="' + wid + '"]');
            if (cfgItem) {
                cfgItem.querySelectorAll('.dash-cfg-size').forEach(function(b) { b.classList.remove('active'); });
                var match = cfgItem.querySelector('.dash-cfg-size[data-size="' + size + '"]');
                if (match) match.classList.add('active');
            }

            self.resizing = null;
            self.saveConfig();
        });
    },

    // ==========================================
    // Hide buttons (X on each cell)
    // ==========================================
    initHideButtons: function() {
        var self = this;
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.dash-hide-btn');
            if (!btn) return;
            e.stopPropagation();

            var cell = btn.closest('.dash-cell');
            if (!cell) return;
            var wid = cell.dataset.widget;

            // Animate out
            cell.style.transition = 'opacity 0.3s, transform 0.3s';
            cell.style.opacity = '0';
            cell.style.transform = 'scale(0.95)';
            setTimeout(function() {
                cell.classList.add('dash-hidden');
                cell.style.opacity = '';
                cell.style.transform = '';
                cell.style.transition = '';
                // Update config panel toggle
                var toggle = document.querySelector('.dash-config-toggle[data-widget="' + wid + '"]');
                if (toggle) toggle.checked = false;
                self.saveConfig();
            }, 300);
        });
    },

    // ==========================================
    // Grid Drag & Drop (drag from widget header)
    // ==========================================
    initGridDragDrop: function() {
        var self = this;
        var grid = document.getElementById('dash-sortable-grid');
        if (!grid) return;

        grid.addEventListener('mousedown', function(e) {
            if (!self.editMode) return;
            var header = e.target.closest('.dash-widget-header');
            if (!header) return;
            if (e.target.closest('button') || e.target.closest('a') || e.target.closest('.dash-widget-action')) return;
            var cell = header.closest('.dash-cell');
            if (cell) cell.setAttribute('draggable', 'true');
        });

        grid.addEventListener('dragstart', function(e) {
            var cell = e.target.closest('.dash-cell');
            if (!cell || cell.classList.contains('dash-drop-placeholder')) { e.preventDefault(); return; }
            self.dragEl = cell;
            cell.classList.add('dash-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', cell.dataset.widget);

            var size = cell.dataset.size || '12';
            self.placeholder.className = 'dash-cell dash-w-' + size + ' dash-drop-placeholder';
        });

        grid.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (!self.dragEl) return;

            var target = e.target.closest('.dash-cell:not(.dash-drop-placeholder):not(.dash-dragging)');
            if (!target) return;

            var rect = target.getBoundingClientRect();
            var midX = rect.left + rect.width / 2;

            if (e.clientX > midX) {
                if (target.nextSibling !== self.placeholder) {
                    grid.insertBefore(self.placeholder, target.nextSibling);
                }
            } else {
                if (self.placeholder.nextSibling !== target) {
                    grid.insertBefore(self.placeholder, target);
                }
            }
        });

        grid.addEventListener('drop', function(e) {
            e.preventDefault();
            if (!self.dragEl) return;

            if (self.placeholder.parentNode === grid) {
                grid.insertBefore(self.dragEl, self.placeholder);
                self.placeholder.parentNode.removeChild(self.placeholder);
            }

            self.dragEl.classList.add('dash-just-dropped');
            setTimeout(function() { self.dragEl.classList.remove('dash-just-dropped'); }, 400);

            self.syncConfigOrder();
            self.saveConfig();
        });

        grid.addEventListener('dragend', function() {
            if (self.dragEl) {
                self.dragEl.classList.remove('dash-dragging');
                self.dragEl.removeAttribute('draggable');
                self.dragEl = null;
            }
            if (self.placeholder.parentNode) {
                self.placeholder.parentNode.removeChild(self.placeholder);
            }
        });
    },

    // ==========================================
    // Config Panel Drag & Drop
    // ==========================================
    initConfigDragDrop: function() {
        var self = this;
        var list = document.getElementById('dash-config-list');
        if (!list) return;
        var dragItem = null;

        list.addEventListener('mousedown', function(e) {
            var handle = e.target.closest('.drag-handle');
            if (!handle) return;
            var item = handle.closest('.dash-config-item');
            if (item) item.setAttribute('draggable', 'true');
        });

        list.addEventListener('dragstart', function(e) {
            var item = e.target.closest('.dash-config-item');
            if (!item) return;
            dragItem = item;
            item.classList.add('cfg-dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        list.addEventListener('dragover', function(e) {
            e.preventDefault();
            var target = e.target.closest('.dash-config-item');
            list.querySelectorAll('.dash-config-item').forEach(function(i) { i.classList.remove('cfg-drag-over'); });
            if (target && target !== dragItem) target.classList.add('cfg-drag-over');
        });

        list.addEventListener('drop', function(e) {
            e.preventDefault();
            var target = e.target.closest('.dash-config-item');
            list.querySelectorAll('.dash-config-item').forEach(function(i) { i.classList.remove('cfg-drag-over'); });
            if (!target || !dragItem || target === dragItem) return;
            var items = Array.from(list.querySelectorAll('.dash-config-item'));
            var dIdx = items.indexOf(dragItem);
            var tIdx = items.indexOf(target);
            if (dIdx < tIdx) {
                target.parentNode.insertBefore(dragItem, target.nextSibling);
            } else {
                target.parentNode.insertBefore(dragItem, target);
            }
            self.syncGridOrder();
            self.saveConfig();
        });

        list.addEventListener('dragend', function() {
            if (dragItem) {
                dragItem.classList.remove('cfg-dragging');
                dragItem.removeAttribute('draggable');
                dragItem = null;
            }
        });
    },

    // ==========================================
    // Config Panel size buttons
    // ==========================================
    initConfigResize: function() {
        var self = this;
        document.addEventListener('click', function(e) {
            var cfgBtn = e.target.closest('.dash-cfg-size');
            if (!cfgBtn) return;
            var item = cfgBtn.closest('.dash-config-item');
            var wid = item.dataset.widget;
            var size = parseInt(cfgBtn.dataset.size);
            item.querySelectorAll('.dash-cfg-size').forEach(function(b) { b.classList.remove('active'); });
            cfgBtn.classList.add('active');
            var cell = document.querySelector('.dash-cell[data-widget="' + wid + '"]');
            if (cell) {
                cell.className = cell.className.replace(/dash-w-\d+/g, '');
                cell.classList.add('dash-w-' + size);
                cell.dataset.size = size;
            }
            self.saveConfig();
        });
    },

    // ==========================================
    // Sync grid ↔ config panel order
    // ==========================================
    syncConfigOrder: function() {
        var list = document.getElementById('dash-config-list');
        var grid = document.getElementById('dash-sortable-grid');
        if (!list || !grid) return;
        Array.from(grid.querySelectorAll('.dash-cell:not(.dash-drop-placeholder)')).forEach(function(c) {
            var item = list.querySelector('.dash-config-item[data-widget="' + c.dataset.widget + '"]');
            if (item) list.appendChild(item);
        });
    },

    syncGridOrder: function() {
        var list = document.getElementById('dash-config-list');
        var grid = document.getElementById('dash-sortable-grid');
        if (!list || !grid) return;
        Array.from(list.querySelectorAll('.dash-config-item')).forEach(function(i) {
            var cell = grid.querySelector('.dash-cell[data-widget="' + i.dataset.widget + '"]');
            if (cell) grid.appendChild(cell);
        });
    },

    // ==========================================
    // Config Panel open/close + toggle visibility
    // ==========================================
    initConfigPanel: function() {
        var self = this;
        document.addEventListener('click', function(e) {
            if (e.target.closest('#dash-config-btn')) {
                e.preventDefault();
                document.getElementById('dash-config-overlay').classList.add('active');
                document.getElementById('dash-config-panel').classList.add('active');
            }
            if (e.target.closest('#dash-config-close') || e.target.id === 'dash-config-overlay') {
                document.getElementById('dash-config-overlay').classList.remove('active');
                document.getElementById('dash-config-panel').classList.remove('active');
            }
        });
        // Toggle visibility from config panel (add/remove widget)
        document.addEventListener('change', function(e) {
            if (!e.target.classList.contains('dash-config-toggle')) return;
            var wid = e.target.dataset.widget;
            var visible = e.target.checked;
            var cell = document.querySelector('.dash-cell[data-widget="' + wid + '"]');
            if (cell) {
                if (visible) {
                    cell.classList.remove('dash-hidden');
                    cell.style.opacity = '0';
                    cell.style.transform = 'scale(0.95)';
                    setTimeout(function() {
                        cell.style.transition = 'opacity 0.3s, transform 0.3s';
                        cell.style.opacity = '1';
                        cell.style.transform = 'scale(1)';
                        setTimeout(function() {
                            cell.style.transition = '';
                            cell.style.transform = '';
                        }, 300);
                    }, 10);
                } else {
                    cell.classList.add('dash-hidden');
                }
            }
            self.saveConfig();
        });
    },

    // ==========================================
    // Edit Mode (visual indicator)
    // ==========================================
    initEditMode: function() {
        var self = this;
        var btn = document.getElementById('dash-edit-btn');
        var resetBtn = document.getElementById('dash-reset-btn');
        if (!btn) return;

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            self.editMode = !self.editMode;
            document.querySelector('.dashboard-wrap').classList.toggle('dash-edit-mode', self.editMode);
            btn.classList.toggle('active', self.editMode);
            btn.innerHTML = self.editMode
                ? '<i class="fa fa-check"></i> Guardar Layout'
                : '<i class="fa fa-th-large"></i> Editar Layout';
            // Show/hide reset button
            if (resetBtn) resetBtn.style.display = self.editMode ? '' : 'none';
            if (!self.editMode) self.saveConfig();
        });

        // Reset to defaults
        if (resetBtn) {
            resetBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (!confirm('Se restaurara el layout por defecto. ¿Continuar?')) return;
                var xhr = new XMLHttpRequest();
                xhr.open('POST', self.configUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() { window.location.reload(); };
                xhr.send('dash_action=reset_config');
            });
        }
    },

    // ==========================================
    // Charts
    // ==========================================
    initSalesChart: function() {
        var ctx = document.getElementById('salesChart');
        if (!ctx || typeof dashSalesData === 'undefined') return;
        var data = dashSalesData['30days'];
        this.charts.sales = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Ventas', data: data.sales,
                    borderColor: '#5d9cec', backgroundColor: 'rgba(93,156,236,0.08)',
                    borderWidth: 2.5, fill: true, tension: 0.4,
                    pointRadius: 0, pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#5d9cec', pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2,
                    yAxisID: 'y'
                }, {
                    label: 'Pedidos', data: data.orders, type: 'bar',
                    backgroundColor: 'rgba(141,202,53,0.3)', borderColor: '#8dca35',
                    borderWidth: 1, borderRadius: 4, yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: true, position: 'top', align: 'end', labels: { usePointStyle: true, pointStyle: 'circle', padding: 16, font: { size: 12 } } },
                    tooltip: {
                        backgroundColor: '#fff', titleColor: '#4c5667', bodyColor: '#4c5667',
                        borderColor: '#e8ecf1', borderWidth: 1, padding: 12, displayColors: true,
                        callbacks: {
                            label: function(c) {
                                return c.dataset.label === 'Ventas'
                                    ? ' Ventas: ' + c.parsed.y.toLocaleString('es-ES', {minimumFractionDigits:2}) + ' \u20AC'
                                    : ' Pedidos: ' + c.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 45, maxTicksLimit: 15 } },
                    y: { position: 'left', grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 11 }, callback: function(v) { return v.toLocaleString('es-ES') + ' \u20AC'; } } },
                    y1: { position: 'right', grid: { display: false }, ticks: { font: { size: 11 }, stepSize: 1 } }
                }
            }
        });
    },

    updateSalesChart: function(period) {
        if (!this.charts.sales || typeof dashSalesData === 'undefined') return;
        var d = dashSalesData[period];
        this.charts.sales.data.labels = d.labels;
        this.charts.sales.data.datasets[0].data = d.sales;
        this.charts.sales.data.datasets[1].data = d.orders;
        this.charts.sales.update('active');
    },

    ordersStatusPeriod: 'today',

    initOrdersStatusChart: function() {
        var ctx = document.getElementById('ordersStatusChart');
        if (!ctx || typeof dashOrdersStatusData === 'undefined') return;
        var period = this.ordersStatusPeriod;
        var d = dashOrdersStatusData[period] || dashOrdersStatusData['all'];
        this.charts.ordersStatus = new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: d.labels,
                datasets: [{ data: d.data, backgroundColor: d.colors, borderWidth: 2, borderColor: '#fff', hoverOffset: 8 }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '65%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#fff', titleColor: '#4c5667', bodyColor: '#4c5667',
                        borderColor: '#e8ecf1', borderWidth: 1, padding: 12,
                        callbacks: {
                            label: function(c) {
                                var t = c.dataset.data.reduce(function(a,b){return a+b;},0);
                                return ' ' + c.label + ': ' + c.parsed.toLocaleString('es-ES') + ' (' + ((c.parsed/t)*100).toFixed(1) + '%)';
                            }
                        }
                    }
                }
            }
        });
    },

    updateOrdersStatusChart: function(period) {
        this.ordersStatusPeriod = period;
        var chart = this.charts.ordersStatus;
        if (!chart || typeof dashOrdersStatusData === 'undefined') return;
        var d = dashOrdersStatusData[period] || dashOrdersStatusData['all'];
        chart.data.labels = d.labels;
        chart.data.datasets[0].data = d.data;
        chart.data.datasets[0].backgroundColor = d.colors;
        chart.update('active');
    },

    initTabs: function() {
        var self = this;
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-chart-period]');
            if (btn) {
                btn.parentElement.querySelectorAll('.dash-chart-tab').forEach(function(b) { b.classList.remove('active'); });
                btn.classList.add('active');
                self.updateSalesChart(btn.dataset.chartPeriod);
            }
            var pay = e.target.closest('[data-pay-tab]');
            if (pay) {
                pay.parentElement.querySelectorAll('.dash-chart-tab').forEach(function(b) { b.classList.remove('active'); });
                pay.classList.add('active');
                document.querySelectorAll('.dash-pay-content').forEach(function(el) { el.style.display = 'none'; });
                var target = document.getElementById('pay-tab-' + pay.dataset.payTab);
                if (target) target.style.display = '';
            }
            // Orders status period tabs
            var osTab = e.target.closest('[data-os-tab]');
            if (osTab) {
                osTab.parentElement.querySelectorAll('.dash-chart-tab').forEach(function(b) { b.classList.remove('active'); });
                osTab.classList.add('active');
                document.querySelectorAll('.dash-os-tab-content').forEach(function(el) { el.style.display = 'none'; });
                var osTarget = document.getElementById('os-tab-' + osTab.dataset.osTab);
                if (osTarget) osTarget.style.display = '';
                self.updateOrdersStatusChart(osTab.dataset.osTab);
            }
        });
    },

    animateNumbers: function() {
        document.querySelectorAll('.dash-kpi-value').forEach(function(el) {
            el.style.opacity = '0';
            el.style.transform = 'scale(0.8)';
            setTimeout(function() {
                el.style.transition = 'all 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
                el.style.opacity = '1';
                el.style.transform = 'scale(1)';
            }, 100);
        });
    },

    // ==========================================
    // Config Panel: KPI toggles + drag reorder
    // ==========================================
    initConfigKpis: function() {
        var self = this;
        var list = document.getElementById('dash-config-kpis');
        if (!list) return;

        // Toggle visibility from config panel
        document.addEventListener('change', function(e) {
            if (!e.target.classList.contains('dash-config-kpi-toggle')) return;
            var kid = e.target.dataset.kpi;
            var visible = e.target.checked;
            var kpi = document.querySelector('.dash-kpi[data-kpi="' + kid + '"]');
            if (kpi) {
                if (visible) {
                    kpi.classList.remove('dash-kpi-hidden');
                    kpi.style.opacity = '0';
                    kpi.style.transform = 'scale(0.9)';
                    setTimeout(function() {
                        kpi.style.transition = 'opacity 0.3s, transform 0.3s';
                        kpi.style.opacity = '1';
                        kpi.style.transform = 'scale(1)';
                        setTimeout(function() { kpi.style.transition = ''; kpi.style.transform = ''; }, 300);
                    }, 10);
                } else {
                    kpi.classList.add('dash-kpi-hidden');
                }
            }
            self.saveConfig();
        });

        // Drag reorder in config panel for KPIs
        var dragItem = null;

        list.addEventListener('mousedown', function(e) {
            var handle = e.target.closest('.drag-handle');
            if (!handle) return;
            var item = handle.closest('.dash-config-kpi-item');
            if (item) item.setAttribute('draggable', 'true');
        });

        list.addEventListener('dragstart', function(e) {
            var item = e.target.closest('.dash-config-kpi-item');
            if (!item) return;
            dragItem = item;
            item.classList.add('cfg-dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        list.addEventListener('dragover', function(e) {
            e.preventDefault();
            var target = e.target.closest('.dash-config-kpi-item');
            list.querySelectorAll('.dash-config-kpi-item').forEach(function(i) { i.classList.remove('cfg-drag-over'); });
            if (target && target !== dragItem) target.classList.add('cfg-drag-over');
        });

        list.addEventListener('drop', function(e) {
            e.preventDefault();
            var target = e.target.closest('.dash-config-kpi-item');
            list.querySelectorAll('.dash-config-kpi-item').forEach(function(i) { i.classList.remove('cfg-drag-over'); });
            if (!target || !dragItem || target === dragItem) return;
            var items = Array.from(list.querySelectorAll('.dash-config-kpi-item'));
            if (items.indexOf(dragItem) < items.indexOf(target)) {
                target.parentNode.insertBefore(dragItem, target.nextSibling);
            } else {
                target.parentNode.insertBefore(dragItem, target);
            }
            // Sync KPI grid order from config panel
            self.syncKpiGridOrder();
            self.saveConfig();
        });

        list.addEventListener('dragend', function() {
            if (dragItem) {
                dragItem.classList.remove('cfg-dragging');
                dragItem.removeAttribute('draggable');
                dragItem = null;
            }
        });
    },

    // Sync KPI grid order from config panel order
    syncKpiGridOrder: function() {
        var list = document.getElementById('dash-config-kpis');
        var grid = document.getElementById('dash-kpis-grid');
        if (!list || !grid) return;
        Array.from(list.querySelectorAll('.dash-config-kpi-item')).forEach(function(item) {
            var kpi = grid.querySelector('.dash-kpi[data-kpi="' + item.dataset.kpi + '"]');
            if (kpi) grid.appendChild(kpi);
        });
    },

    // Sync config panel KPI order from grid
    syncConfigKpiOrder: function() {
        var list = document.getElementById('dash-config-kpis');
        var grid = document.getElementById('dash-kpis-grid');
        if (!list || !grid) return;
        Array.from(grid.querySelectorAll('.dash-kpi')).forEach(function(kpi) {
            var item = list.querySelector('.dash-config-kpi-item[data-kpi="' + kpi.dataset.kpi + '"]');
            if (item) list.appendChild(item);
        });
    },

    // ==========================================
    // KPI Drag & Drop (reorder KPI cards in edit mode)
    // ==========================================
    initKpiDragDrop: function() {
        var self = this;
        var grid = document.getElementById('dash-kpis-grid');
        if (!grid) return;
        var dragKpi = null;

        grid.addEventListener('mousedown', function(e) {
            if (!self.editMode) return;
            if (e.target.closest('.dash-kpi-hide-btn')) return;
            var kpi = e.target.closest('.dash-kpi');
            if (kpi) kpi.setAttribute('draggable', 'true');
        });

        grid.addEventListener('dragstart', function(e) {
            var kpi = e.target.closest('.dash-kpi');
            if (!kpi || !self.editMode) { e.preventDefault(); return; }
            dragKpi = kpi;
            kpi.classList.add('dash-kpi-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', kpi.dataset.kpi);
        });

        grid.addEventListener('dragover', function(e) {
            e.preventDefault();
            if (!dragKpi) return;
            var target = e.target.closest('.dash-kpi:not(.dash-kpi-dragging)');
            grid.querySelectorAll('.dash-kpi').forEach(function(k) { k.classList.remove('dash-kpi-drop-target'); });
            if (target) target.classList.add('dash-kpi-drop-target');
        });

        grid.addEventListener('drop', function(e) {
            e.preventDefault();
            var target = e.target.closest('.dash-kpi:not(.dash-kpi-dragging)');
            grid.querySelectorAll('.dash-kpi').forEach(function(k) { k.classList.remove('dash-kpi-drop-target'); });
            if (!target || !dragKpi || target === dragKpi) return;
            var kpis = Array.from(grid.querySelectorAll('.dash-kpi'));
            var dIdx = kpis.indexOf(dragKpi);
            var tIdx = kpis.indexOf(target);
            if (dIdx < tIdx) {
                target.parentNode.insertBefore(dragKpi, target.nextSibling);
            } else {
                target.parentNode.insertBefore(dragKpi, target);
            }
            dragKpi.classList.add('dash-kpi-just-dropped');
            setTimeout(function() { dragKpi.classList.remove('dash-kpi-just-dropped'); }, 400);
            self.syncConfigKpiOrder();
            self.saveConfig();
        });

        grid.addEventListener('dragend', function() {
            if (dragKpi) {
                dragKpi.classList.remove('dash-kpi-dragging');
                dragKpi.removeAttribute('draggable');
                dragKpi = null;
            }
        });
    },

    // ==========================================
    // KPI Edge Resize (drag right edge in edit mode)
    // ==========================================
    initKpiEdgeResize: function() {
        var self = this;
        var grid = document.getElementById('dash-kpis-grid');
        if (!grid) return;

        var resizingKpi = null, startX = 0, startSize = 0;

        function getGridWidth() { return grid.getBoundingClientRect().width; }

        function calcKpiSize(startSz, deltaX, gridW) {
            var colW = gridW / 12;
            var deltaCols = Math.round(deltaX / colW);
            var newSz = startSz + deltaCols;
            var best = self.kpiSizes[0], bestD = Math.abs(newSz - best);
            for (var i = 1; i < self.kpiSizes.length; i++) {
                var d = Math.abs(newSz - self.kpiSizes[i]);
                if (d < bestD) { best = self.kpiSizes[i]; bestD = d; }
            }
            return best;
        }

        document.addEventListener('mousedown', function(e) {
            if (!self.editMode) return;
            var handle = e.target.closest('.dash-kpi-resize-handle');
            if (!handle) return;
            e.preventDefault(); e.stopPropagation();
            var kpi = handle.closest('.dash-kpi');
            if (!kpi) return;
            resizingKpi = kpi;
            startX = e.clientX;
            startSize = parseInt(kpi.dataset.kpiSize) || 2;
            kpi.classList.add('dash-kpi-resizing');
            handle.classList.add('active');
            var ind = kpi.querySelector('.dash-kpi-size-indicator');
            if (ind) ind.textContent = self.kpiSizeLabels[startSize];
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
        });

        document.addEventListener('mousemove', function(e) {
            if (!resizingKpi) return;
            e.preventDefault();
            var deltaX = e.clientX - startX;
            var newSz = calcKpiSize(startSize, deltaX, getGridWidth());
            var curSz = parseInt(resizingKpi.dataset.kpiSize) || 2;
            if (newSz !== curSz) {
                resizingKpi.className = resizingKpi.className.replace(/dash-kpi-w-\d+/g, '');
                resizingKpi.classList.add('dash-kpi-w-' + newSz);
                resizingKpi.classList.add('dash-kpi-resizing');
                resizingKpi.dataset.kpiSize = newSz;
            }
            var ind = resizingKpi.querySelector('.dash-kpi-size-indicator');
            if (ind) ind.textContent = self.kpiSizeLabels[newSz];
        });

        document.addEventListener('mouseup', function(e) {
            if (!resizingKpi) return;
            resizingKpi.classList.remove('dash-kpi-resizing');
            var handle = resizingKpi.querySelector('.dash-kpi-resize-handle');
            if (handle) handle.classList.remove('active');
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            // Sync config panel
            var kid = resizingKpi.dataset.kpi;
            var sz = parseInt(resizingKpi.dataset.kpiSize) || 2;
            var cfgItem = document.querySelector('.dash-config-kpi-item[data-kpi="' + kid + '"]');
            if (cfgItem) {
                cfgItem.querySelectorAll('.dash-cfg-kpi-size').forEach(function(b) { b.classList.remove('active'); });
                var match = cfgItem.querySelector('.dash-cfg-kpi-size[data-kpi-size="' + sz + '"]');
                if (match) match.classList.add('active');
            }
            resizingKpi = null;
            self.saveConfig();
        });
    },

    // ==========================================
    // Config Panel: KPI size buttons
    // ==========================================
    initConfigKpiResize: function() {
        var self = this;
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.dash-cfg-kpi-size');
            if (!btn) return;
            var item = btn.closest('.dash-config-kpi-item');
            if (!item) return;
            var kid = item.dataset.kpi;
            var sz = parseInt(btn.dataset.kpiSize);
            item.querySelectorAll('.dash-cfg-kpi-size').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            var kpi = document.querySelector('.dash-kpi[data-kpi="' + kid + '"]');
            if (kpi) {
                kpi.className = kpi.className.replace(/dash-kpi-w-\d+/g, '');
                kpi.classList.add('dash-kpi-w-' + sz);
                kpi.dataset.kpiSize = sz;
            }
            self.saveConfig();
        });
    },

    // ==========================================
    // KPI Hide (X button on each card)
    // ==========================================
    initKpiHide: function() {
        var self = this;
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.dash-kpi-hide-btn');
            if (!btn) return;
            e.stopPropagation();
            var kpi = btn.closest('.dash-kpi');
            if (!kpi) return;
            kpi.style.transition = 'opacity 0.3s, transform 0.3s';
            kpi.style.opacity = '0';
            kpi.style.transform = 'scale(0.9)';
            setTimeout(function() {
                kpi.classList.add('dash-kpi-hidden');
                kpi.style.opacity = '';
                kpi.style.transform = '';
                kpi.style.transition = '';
                // Sync config panel toggle
                var toggle = document.querySelector('.dash-config-kpi-toggle[data-kpi="' + kpi.dataset.kpi + '"]');
                if (toggle) toggle.checked = false;
                self.saveConfig();
            }, 300);
        });
    },

    // ==========================================
    // Auto-Refresh (configurable interval)
    // ==========================================
    initAutoRefresh: function() {
        var self = this;
        var options = document.getElementById('dash-refresh-options');
        var label = document.getElementById('dash-refresh-label');
        var dot = document.getElementById('dash-refresh-dot');
        if (!options) return;

        // Load saved interval
        var xhr = new XMLHttpRequest();
        xhr.open('GET', self.configUrl + '?dash_action=load_config', true);
        xhr.onload = function() {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.config && typeof resp.config.autoRefresh !== 'undefined') {
                    self.refreshMinutes = parseInt(resp.config.autoRefresh);
                }
            } catch(e) {}
            self.applyRefresh(label, dot);
            self.highlightRefreshOpts(options);
        };
        xhr.send();

        // Click on option in config panel
        options.addEventListener('click', function(e) {
            var opt = e.target.closest('.dash-refresh-opt');
            if (!opt) return;
            self.refreshMinutes = parseInt(opt.dataset.minutes);
            self.applyRefresh(label, dot);
            self.highlightRefreshOpts(options);
            self.saveConfig();
        });
    },

    applyRefresh: function(label, dot) {
        var self = this;
        if (self.refreshTimer) { clearInterval(self.refreshTimer); self.refreshTimer = null; }
        if (self.refreshMinutes > 0) {
            if (label) label.textContent = 'Auto-refresh: ' + self.refreshMinutes + 'm';
            if (dot) dot.classList.remove('off');
            self.refreshTimer = setInterval(function() { window.location.reload(); }, self.refreshMinutes * 60 * 1000);
        } else {
            if (label) label.textContent = 'Auto-refresh: Off';
            if (dot) dot.classList.add('off');
        }
    },

    highlightRefreshOpts: function(container) {
        var self = this;
        container.querySelectorAll('.dash-refresh-opt').forEach(function(opt) {
            opt.classList.toggle('active', parseInt(opt.dataset.minutes) === self.refreshMinutes);
        });
    },

    // ==========================================
    // Save Config (server)
    // ==========================================
    saveConfig: function() {
        var grid = document.getElementById('dash-sortable-grid');
        if (!grid) return;
        var order = [];
        var widgets = {};
        grid.querySelectorAll('.dash-cell:not(.dash-drop-placeholder)').forEach(function(cell) {
            var wid = cell.dataset.widget;
            order.push(wid);
            var toggle = document.querySelector('.dash-config-toggle[data-widget="' + wid + '"]');
            widgets[wid] = {
                size: parseInt(cell.dataset.size) || 12,
                visible: toggle ? toggle.checked : !cell.classList.contains('dash-hidden')
            };
        });
        // KPI order + hidden + sizes
        var kpiOrder = [];
        var kpiHidden = [];
        var kpiSizes = {};
        var kpiGrid = document.getElementById('dash-kpis-grid');
        if (kpiGrid) {
            kpiGrid.querySelectorAll('.dash-kpi').forEach(function(kpi) {
                var kid = kpi.dataset.kpi;
                kpiOrder.push(kid);
                if (kpi.classList.contains('dash-kpi-hidden')) kpiHidden.push(kid);
                var sz = parseInt(kpi.dataset.kpiSize) || 2;
                if (sz !== 2) kpiSizes[kid] = sz; // solo guardar si no es el default
            });
        }

        var config = JSON.stringify({ order: order, widgets: widgets, kpis: { order: kpiOrder, hidden: kpiHidden, sizes: kpiSizes }, autoRefresh: this.refreshMinutes });
        var xhr = new XMLHttpRequest();
        xhr.open('POST', this.configUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.status !== 'ok') console.warn('Dashboard save error:', resp.message || resp);
            } catch(e) {
                console.error('Dashboard save: invalid response', xhr.responseText);
            }
        };
        xhr.onerror = function() { console.error('Dashboard save: network error'); };
        xhr.send('dash_action=save_config&config=' + encodeURIComponent(config));
    }
};

function dashRefreshWidget(widgetId) {
    var w = document.querySelector('[data-widget="' + widgetId + '"]');
    if (w) { w.classList.add('refreshing'); setTimeout(function() { w.classList.remove('refreshing'); }, 1200); }
}

document.addEventListener('DOMContentLoaded', function() {
    DashBoard.init();
});
