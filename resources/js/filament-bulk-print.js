// resources/js/filament-bulk-print.js
// Add this script to handle bulk printing functionality

document.addEventListener("DOMContentLoaded", function () {
    // Check if we have URLs to open from session
    if (window.openUrls && Array.isArray(window.openUrls)) {
        // Open each URL in a new tab with a small delay
        window.openUrls.forEach((url, index) => {
            setTimeout(() => {
                window.open(url, "_blank");
            }, index * 100); // 100ms delay between each tab
        });
    }
});

// Function to handle bulk printing action
function handleBulkPrint(urls) {
    if (!urls || !Array.isArray(urls)) {
        alert("Tidak ada tagihan yang dipilih untuk dicetak.");
        return;
    }

    if (urls.length > 10) {
        if (
            !confirm(
                `Anda akan membuka ${urls.length} tab baru. Pastikan browser tidak memblokir popup. Lanjutkan?`
            )
        ) {
            return;
        }
    }

    // Open each URL with a small delay to prevent browser blocking
    urls.forEach((url, index) => {
        setTimeout(() => {
            const newWindow = window.open(url, "_blank");
            if (!newWindow) {
                console.warn("Popup blocked for URL:", url);
            }
        }, index * 150); // 150ms delay between each tab
    });
}

// Add event listener for print buttons in tables
document.addEventListener("click", function (e) {
    // Handle individual print receipt buttons
    if (e.target.matches(".print-receipt-btn, .print-receipt-btn *")) {
        e.preventDefault();
        const button = e.target.closest(".print-receipt-btn");
        const url = button.href;

        if (url) {
            window.open(url, "_blank", "width=800,height=600,scrollbars=yes");
        }
    }
});
