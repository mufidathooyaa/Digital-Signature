// Auto-hide alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });
    
    // Validasi tanggal
    const tanggalMulai = document.getElementById('tanggal_mulai');
    const tanggalSelesai = document.getElementById('tanggal_selesai');
    
    if (tanggalMulai && tanggalSelesai) {
        tanggalMulai.addEventListener('change', function() {
            tanggalSelesai.min = this.value;
        });
        
        tanggalSelesai.addEventListener('change', function() {
            if (this.value < tanggalMulai.value) {
                alert('Tanggal selesai tidak boleh lebih awal dari tanggal mulai!');
                this.value = tanggalMulai.value;
            }
        });
    }
    
    // Konfirmasi sebelum submit form penting
    const importantForms = document.querySelectorAll('form[data-confirm]');
    importantForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
});

// Copy to clipboard function
function copyToClipboard(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
    alert('Teks berhasil disalin!');
}

// Print function
function printDocument() {
    window.print();
}

// Format number
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// Show loading
function showLoading() {
    const loading = document.createElement('div');
    loading.className = 'loading-overlay';
    loading.innerHTML = '<div class="spinner"></div>';
    document.body.appendChild(loading);
}

function hideLoading() {
    const loading = document.querySelector('.loading-overlay');
    if (loading) {
        loading.remove();
    }
}