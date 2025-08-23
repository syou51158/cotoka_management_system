/**
 * サービス管理ページのJavaScript
 */
document.addEventListener('DOMContentLoaded', function() {
    // 編集モーダルを自動的に表示（URL内のaction=editがある場合）
    if (window.location.href.includes('action=edit')) {
        const editModal = document.getElementById('editServiceModal');
        if (editModal) {
            var bsModal = new bootstrap.Modal(editModal);
            bsModal.show();
        }
    }
    
    // DataTables初期化
    const servicesTable = $('#servicesTable').DataTable({
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.10.25/i18n/Japanese.json'
        },
        responsive: true,
        stateSave: true,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "すべて"]],
        order: [[0, "asc"]],
        columnDefs: [
            { orderable: false, targets: 5 } // 操作列はソート不可
        ]
    });

    // 削除モーダルの設定
    $('#deleteServiceModal').on('show.bs.modal', function(event) {
        const button = $(event.relatedTarget);
        const serviceId = button.data('id');
        const serviceName = button.data('name');
        
        $('#deleteServiceName').text(serviceName);
        $('#confirmDeleteBtn').attr('href', `services.php?action=delete&id=${serviceId}`);
    });

    // ビュー切り替えボタン
    const viewToggleButtons = document.querySelectorAll('.view-toggle-btn');
    const servicesContainer = document.getElementById('services-container');
    
    if (servicesContainer && viewToggleButtons.length > 0) {
        viewToggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const viewType = this.getAttribute('data-view');
                
                // アクティブクラスの切り替え
                viewToggleButtons.forEach(btn => {
                    btn.classList.remove('active');
                });
                this.classList.add('active');
                
                // 表示形式の切り替え
                if (viewType === 'card') {
                    servicesContainer.classList.remove('list-view');
                    servicesContainer.classList.add('card-view');
                } else {
                    servicesContainer.classList.remove('card-view');
                    servicesContainer.classList.add('list-view');
                }
                
                // ローカルストレージに表示設定を保存
                localStorage.setItem('services_view_type', viewType);
            });
        });
        
        // 保存された表示設定を適用
        const savedViewType = localStorage.getItem('services_view_type');
        if (savedViewType) {
            const targetButton = document.querySelector(`.view-toggle-btn[data-view="${savedViewType}"]`);
            if (targetButton) {
                targetButton.click();
            }
        }
    }
    
    // 検索機能
    const searchInput = document.getElementById('service-search');
    if (searchInput) {
        searchInput.addEventListener('input', filterServices);
    }
    
    // カテゴリーフィルター
    const categoryFilters = document.querySelectorAll('.category-filter');
    if (categoryFilters.length > 0) {
        categoryFilters.forEach(filter => {
            filter.addEventListener('click', function() {
                categoryFilters.forEach(f => f.classList.remove('active'));
                this.classList.add('active');
                filterServices();
            });
        });
    }
    
    // ステータスフィルター
    const statusFilter = document.getElementById('status-filter');
    if (statusFilter) {
        statusFilter.addEventListener('change', filterServices);
    }
    
    // ソート機能
    const sortOptions = document.getElementById('sort-options');
    if (sortOptions) {
        sortOptions.addEventListener('change', sortServices);
    }
    
    // サービスのフィルタリング関数
    function filterServices() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const selectedCategory = document.querySelector('.category-filter.active').getAttribute('data-category');
        const selectedStatus = statusFilter ? statusFilter.value : 'all';
        const serviceItems = document.querySelectorAll('.service-item');
        let visibleCount = 0;
        
        serviceItems.forEach(item => {
            const name = item.getAttribute('data-name').toLowerCase();
            const category = item.getAttribute('data-category');
            const status = item.getAttribute('data-status');
            
            const matchesSearch = name.includes(searchTerm);
            const matchesCategory = selectedCategory === 'all' || category === selectedCategory;
            const matchesStatus = selectedStatus === 'all' || status === selectedStatus;
            
            if (matchesSearch && matchesCategory && matchesStatus) {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });
        
        // 表示件数の更新
        updateDisplayCount(visibleCount);
        
        // ソート適用
        sortServices();
    }
    
    // サービスのソート関数
    function sortServices() {
        const sortBy = sortOptions ? sortOptions.value : 'name-asc';
        const serviceItems = Array.from(document.querySelectorAll('.service-item'));
        const container = document.querySelector('#services-container .row');
        
        if (!container) return;
        
        serviceItems.sort((a, b) => {
            if (sortBy === 'name-asc') {
                return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
            } else if (sortBy === 'name-desc') {
                return b.getAttribute('data-name').localeCompare(a.getAttribute('data-name'));
            } else if (sortBy === 'price-asc') {
                return parseInt(a.getAttribute('data-price')) - parseInt(b.getAttribute('data-price'));
            } else if (sortBy === 'price-desc') {
                return parseInt(b.getAttribute('data-price')) - parseInt(a.getAttribute('data-price'));
            } else if (sortBy === 'duration-asc') {
                return parseInt(a.getAttribute('data-duration')) - parseInt(b.getAttribute('data-duration'));
            } else if (sortBy === 'duration-desc') {
                return parseInt(b.getAttribute('data-duration')) - parseInt(a.getAttribute('data-duration'));
            } else if (sortBy === 'date-asc') {
                return new Date(a.getAttribute('data-date')) - new Date(b.getAttribute('data-date'));
            } else if (sortBy === 'date-desc') {
                return new Date(b.getAttribute('data-date')) - new Date(a.getAttribute('data-date'));
            }
            return 0;
        });
        
        serviceItems.forEach(item => {
            container.appendChild(item);
        });
    }
    
    // 表示件数の更新
    function updateDisplayCount(count) {
        const countBadge = document.querySelector('.badge.bg-primary');
        if (countBadge) {
            const totalCount = document.querySelectorAll('.service-item').length;
            countBadge.textContent = `全 ${totalCount} 件 (表示: ${count} 件)`;
        }
    }
    
    // 初期フィルター適用
    filterServices();
}); 