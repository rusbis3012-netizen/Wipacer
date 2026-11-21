let inventory = [
            { id: 1, sku: 'TECH-LAP-01', name: 'Laptop Pro 16" M3 Max', category: 'Equipos', warehouse: 'Bodega Central', stock: 18, minStock: 10, cost: 2100, icon: 'laptop' },
            { id: 2, sku: 'TECH-MON-02', name: 'Monitor Curvo UltraWide 34"', category: 'Periféricos', warehouse: 'Almacén Norte', stock: 6, minStock: 12, cost: 450, icon: 'monitor' },
            { id: 3, sku: 'TECH-AUD-03', name: 'Auriculares Hi-Fi Noise Cancelling', category: 'Audio', warehouse: 'Bodega Central', stock: 42, minStock: 15, cost: 240, icon: 'headphones' },
            { id: 4, sku: 'TECH-KEY-04', name: 'Teclado Mecánico Custom RGB', category: 'Periféricos', warehouse: 'Almacén Norte', stock: 8, minStock: 15, cost: 95, icon: 'keyboard' },
            { id: 5, sku: 'TECH-SMP-05', name: 'Smartphone Ultra 5G 256GB', category: 'Equipos', warehouse: 'Bodega Central', stock: 25, minStock: 10, cost: 890, icon: 'smartphone' },
            { id: 6, sku: 'TECH-WAT-06', name: 'Smartwatch Deportivo Pro', category: 'Audio & Relojes', warehouse: 'Depósito Garantías', stock: 4, minStock: 8, cost: 195, icon: 'watch' },
            { id: 7, sku: 'TECH-MOU-07', name: 'Mouse Ergonómico Precision', category: 'Periféricos', warehouse: 'Almacén Norte', stock: 35, minStock: 10, cost: 65, icon: 'mouse' },
            { id: 8, sku: 'TECH-GPU-08', name: 'Tarjeta Gráfica RTX 4070 OC', category: 'Componentes', warehouse: 'Bodega Central', stock: 11, minStock: 5, cost: 540, icon: 'hard-drive' }
        ];

        let currentFilter = 'todos';



        document.addEventListener('DOMContentLoaded', () => {
            renderKPIs();
            renderTable();
            populateModalSelect();
        });

    
        function renderKPIs() {
            const totalStock = inventory.reduce((acc, i) => acc + i.stock, 0);
            const totalValue = inventory.reduce((acc, i) => acc + (i.stock * i.cost), 0);
            const lowStockCount = inventory.filter(i => i.stock <= i.minStock).length;

            document.getElementById('kpiTotalStock').textContent = totalStock.toLocaleString();
            document.getElementById('kpiTotalValue').textContent = '$' + totalValue.toLocaleString();
            document.getElementById('kpiLowStock').textContent = lowStockCount;

            document.getElementById('filterBtnTodos').textContent = `Todos (${inventory.length})`;
            document.getElementById('filterBtnCritico').textContent = `Stock Bajo (${lowStockCount})`;
            document.getElementById('filterBtnOptimo').textContent = `Óptimo (${inventory.length - lowStockCount})`;
        }

        function renderTable() {
            const tbody = document.getElementById('inventoryTableBody');
            const query = document.getElementById('searchInput').value.trim().toLowerCase();

            const filtered = inventory.filter(item => {
                const matchesText = item.sku.toLowerCase().includes(query) || item.name.toLowerCase().includes(query);
                const isCritical = item.stock <= item.minStock;
                let matchesStatus = true;

                if (currentFilter === 'critico') matchesStatus = isCritical;
                if (currentFilter === 'optimo') matchesStatus = !isCritical;

                return matchesText && matchesStatus;
            });

            if (filtered.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-10 text-slate-400">
                            No se encontraron artículos que coincidan con la búsqueda.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = filtered.map(item => {
                const isCritical = item.stock <= item.minStock;
                const percentage = Math.min(100, Math.round((item.stock / (item.minStock * 2)) * 100));

                return `
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="px-5 py-4 font-mono font-bold text-[#6F9FCB]">
                            ${item.sku}
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-[#6F9FCB]">
                                    <i data-lucide="${item.icon}" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <span class="font-bold text-[#243B53] block">${item.name}</span>
                                    <span class="text-[10px] text-slate-400">${item.category}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-slate-600 font-medium">
                            <span class="inline-flex items-center gap-1">
                                <i data-lucide="map-pin" class="w-3 h-3 text-slate-400"></i>
                                ${item.warehouse}
                            </span>
                        </td>
                        <td class="px-5 py-4">
                            <div class="w-36">
                                <div class="flex justify-between text-[11px] mb-1">
                                    <span class="font-bold">${item.stock} uds</span>
                                    <span class="text-slate-400">mín. ${item.minStock}</span>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                    <div class="h-1.5 rounded-full ${isCritical ? 'bg-[#E8847C]' : 'bg-[#A9C7BB]'}" style="width: ${percentage}%"></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 font-semibold text-slate-600">
                            $${item.cost.toLocaleString()}
                        </td>
                        <td class="px-5 py-4 font-black text-[#243B53]">
                            $${(item.stock * item.cost).toLocaleString()}
                        </td>
                        <td class="px-5 py-4">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold border ${isCritical ? 'bg-[#E8847C]/15 text-[#E8847C] border-[#E8847C]/30' : 'bg-[#A9C7BB]/20 text-[#243B53] border-[#A9C7BB]/40'}">
                                ${isCritical ? 'Bajo Stock' : 'Disponible'}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <button onclick="openAdjustModal(${item.id})" class="p-2 rounded-lg text-slate-400 hover:text-[#6F9FCB] hover:bg-[#6F9FCB]/10 transition-colors" title="Ajustar Stock">
                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            lucide.createIcons();
        }

        
        function filterInventory() {
            renderTable();
        }

        function setStatusFilter(status) {
            currentFilter = status;
            document.querySelectorAll('.status-btn').forEach(b => {
                b.className = 'status-btn px-3.5 py-1.5 rounded-lg text-xs font-semibold text-[#243B53] hover:bg-slate-100 transition-all';
            });
            if (event && event.target) {
                event.target.className = 'status-btn px-3.5 py-1.5 rounded-lg text-xs font-bold bg-[#6F9FCB] text-white shadow-sm transition-all';
            }
            renderTable();
        }

    

        function populateModalSelect() {
            const select = document.getElementById('modalItemSelect');
            select.innerHTML = inventory.map(item => `
                <option value="${item.id}">${item.sku} - ${item.name} (${item.stock} uds en ${item.warehouse})</option>
            `).join('');
        }

        function onModalItemSelectChange(id) {
            document.getElementById('modalItemId').value = id;
        }


        function openAdjustModal(id = null) {
            const select = document.getElementById('modalItemSelect');
            if (id) {
                select.value = id;
            }
            document.getElementById('modalItemId').value = select.value;
            document.getElementById('modalQty').value = 5;
            document.getElementById('modalReason').value = '';
            document.getElementById('adjustModal').classList.remove('hidden');
        }

        function closeAdjustModal() {
            document.getElementById('adjustModal').classList.add('hidden');
        }

        function handleStockSubmit(e) {
            e.preventDefault();
            const id = parseInt(document.getElementById('modalItemSelect').value, 10);
            const type = document.getElementById('modalType').value;
            const qty = parseInt(document.getElementById('modalQty').value, 10);

            const item = inventory.find(i => i.id === id);
            if (!item) return;

            if (type === 'entrada') {
                item.stock += qty;
            } else {
                item.stock = Math.max(0, item.stock - qty);
            }

            renderKPIs();
            renderTable();
            populateModalSelect();
            closeAdjustModal();

            showToast(`${type === 'entrada' ? '+' : '-'}${qty} uds para ${item.name}`);
        }

        function showToast(msg) {
            const toast = document.getElementById('toast');
            document.getElementById('toastMsg').textContent = msg;
            toast.classList.remove('translate-y-20', 'opacity-0');
            setTimeout(() => {
                toast.classList.add('translate-y-20', 'opacity-0');
            }, 2500);
        }