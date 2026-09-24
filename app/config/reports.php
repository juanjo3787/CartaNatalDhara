<?php

return [
    // Cloudflare closes proxied requests at 120 seconds. Leave enough margin for
    // Laravel to finish the response after Dompdf has rendered a long dossier.
    'pdf_render_timeout' => (int) env('PDF_RENDER_TIMEOUT', 110),
];
