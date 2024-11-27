@if ($returnHtml && false)
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script src="https://unpkg.com/jspdf@latest/dist/jspdf.umd.min.js"></script>
    <script>
        var elementHTML = document.querySelector("body");
        var doc = new window.jspdf.jsPDF({
            unit: "mm",
            format: [100, 100]
        });
        doc.html(elementHTML, {
            callback: function(doc) {
                doc.save('stickers.pdf');
            }
        });
    </script>
@endif
</body>

</html>
