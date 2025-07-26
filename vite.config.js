import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: true, // or '0.0.0.0' to listen on all interfaces
        port: 5173,
        strictPort: true, // prevent Vite from changing the port automatically
    },
});
