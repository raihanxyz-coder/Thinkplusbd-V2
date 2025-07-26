function hexToRgb(hex) { /* For focus styles */
    let r = 0, g = 0, b = 0;
    if (hex.length == 4) {
        r = "0x" + hex[1] + hex[1]; g = "0x" + hex[2] + hex[2]; b = "0x" + hex[3] + hex[3];
    } else if (hex.length == 7) {
        r = "0x" + hex[1] + hex[2]; g = "0x" + hex[3] + hex[4]; b = "0x" + hex[5] + hex[6];
    }
    return "" + +r + "," + +g + "," + +b;
}

const primaryColorCSSVar = getComputedStyle(document.documentElement).getPropertyValue('--primary-color').trim();
if (primaryColorCSSVar) {
    document.documentElement.style.setProperty('--primary-color-rgb', hexToRgb(primaryColorCSSVar));
}

/* --- START: UPDATED JAVASCRIPT FOR SIDEBAR TOGGLE --- */
document.addEventListener('DOMContentLoaded', function() {

    const sidebarToggle = document.getElementById('sidebarToggle');
    const adminSidebar = document.getElementById('adminSidebar');

    if (sidebarToggle && adminSidebar) {
        // Event listener for the toggle button
        sidebarToggle.addEventListener('click', (e) => {
            e.stopPropagation(); // Prevents the click from bubbling up to the document
            adminSidebar.classList.toggle('open');
        });

        // Event listener to close the sidebar when clicking outside of it
         document.addEventListener('click', function(event) {
             if (adminSidebar.classList.contains('open')) {
                 // Check if the click was outside the sidebar and not on the toggle button
                 if (!adminSidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
                     adminSidebar.classList.remove('open');
                 }
             }
         });
    }
});
/* --- END: UPDATED JAVASCRIPT --- */
