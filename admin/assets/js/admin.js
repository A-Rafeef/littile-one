/**
 * Little One Kids Store Admin - JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar Toggle with Backdrop
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    
    function openSidebar() {
        sidebar.classList.add('active');
        if (backdrop) backdrop.classList.add('active');
        document.body.style.overflow = 'hidden'; // Prevent scroll
    }
    
    function closeSidebar() {
        sidebar.classList.remove('active');
        if (backdrop) backdrop.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            if (sidebar.classList.contains('active')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
        
        // Close sidebar when clicking backdrop
        if (backdrop) {
            backdrop.addEventListener('click', closeSidebar);
        }
        
        // Close sidebar when clicking outside (fallback)
        document.addEventListener('click', function(e) {
            if (window.innerWidth < 1024) {
                if (sidebar.classList.contains('active') && 
                    !sidebar.contains(e.target) && 
                    !sidebarToggle.contains(e.target)) {
                    closeSidebar();
                }
            }
        });
        
        // Close sidebar on resize to desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) {
                closeSidebar();
            }
        });
    }
    
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.3s, transform 0.3s';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    
    // Confirm before delete
    const deleteForms = document.querySelectorAll('form[data-confirm]');
    deleteForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const message = form.dataset.confirm || 'Are you sure you want to delete this item?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
    
    // Wrap tables in responsive container (auto-detect)
    const tables = document.querySelectorAll('.card-body > .table');
    tables.forEach(table => {
        if (!table.parentElement.classList.contains('table-responsive')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
    });

    // ===========================
    // Image Upload Loading Overlay
    // ===========================
    
    // Create loading overlay element
    const loadingOverlay = document.createElement('div');
    loadingOverlay.className = 'upload-loading-overlay';
    loadingOverlay.innerHTML = `
        <div class="upload-spinner"></div>
        <div class="upload-loading-text">Uploading images...</div>
        <div class="upload-loading-subtext">Please wait, this may take a moment</div>
    `;
    document.body.appendChild(loadingOverlay);

    // Show loading overlay on form submit with file inputs
    const formsWithFiles = document.querySelectorAll('form[enctype="multipart/form-data"]');
    formsWithFiles.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Check if any file input has files selected
            const fileInputs = form.querySelectorAll('input[type="file"]');
            let hasFiles = false;
            
            fileInputs.forEach(input => {
                if (input.files && input.files.length > 0) {
                    hasFiles = true;
                }
            });

            if (hasFiles) {
                // Show loading overlay
                loadingOverlay.classList.add('active');
                
                // Disable submit button and show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.classList.add('loading');
                    submitBtn.disabled = true;
                }
            }
        });
    });

    // Hide overlay if page errors (fallback)
    window.addEventListener('pageshow', function(e) {
        if (e.persisted) {
            loadingOverlay.classList.remove('active');
            document.querySelectorAll('.btn.loading').forEach(btn => {
                btn.classList.remove('loading');
                btn.disabled = false;
            });
        }
    });
});
