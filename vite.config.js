import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { viteStaticCopy } from 'vite-plugin-static-copy'
// import { rtlcssPlugin } from './vite-rtlcss-plugin'

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/sass/app.scss',
                'resources/css/custom.css',
                'resources/rtl-css/app-rtl.css',
                'resources/rtl-css/custom-rtl.css',
                'resources/js/app.js',
                'resources/js/pages/template.js',
                'resources/js/pages/ace.js',
                'resources/js/pages/apexcharts.js',
                'resources/js/pages/bootstrap-maxlength.js',
                'resources/js/pages/chartjs.js',
                'resources/js/pages/chat.js',
                'resources/js/pages/color-modes.js',
                'resources/js/pages/cropper.js',
                'resources/js/pages/dashboard.js',
                'resources/js/pages/data-table.js',
                'resources/js/pages/demo.js',
                'resources/js/pages/dropify.js',
                'resources/js/pages/dropzone.js',
                'resources/js/pages/easymde.js',
                'resources/js/pages/email.js',
                'resources/js/pages/flatpickr.js',
                'resources/js/pages/form-validation.js',
                'resources/js/pages/fullcalendar.js',
                'resources/js/pages/inputmask.js',
                'resources/js/pages/jquery-steps.js',
                'resources/js/pages/jquery.flot.js',
                'resources/js/pages/owl-carousel.js',
                'resources/js/pages/peity.js',
                'resources/js/pages/pickr.js',
                'resources/js/pages/select2.js',
                'resources/js/pages/sortablejs.js',
                'resources/js/pages/sparkline.js',
                'resources/js/pages/sweet-alert.js',
                'resources/js/pages/tags-input.js',
                'resources/js/pages/tinymce.js',
                'resources/js/pages/typeahead.js',
            ],
            refresh: true,
        }),
        // rtlcssPlugin(),
        viteStaticCopy({
            targets: [
                {
                    src: 'resources/images',
                    dest: ''
                },
                {
                    src: ['node_modules/@mdi/font/*'],
                    dest: 'plugins/@mdi'
                },
                {
                    src: ['node_modules/ace-builds/src-min/*'],
                    dest: 'plugins/ace-builds'
                },
                {
                    src: ['node_modules/animate.css/animate.min.css'],
                    dest: 'plugins/animate-css'
                },
                {
                    src: ['node_modules/apexcharts/dist/apexcharts.min.js'],
                    dest: 'plugins/apexcharts'
                },
                {
                    src: ['node_modules/bootstrap/dist/js/bootstrap.bundle.min.js'],
                    dest: 'plugins/bootstrap'
                },
                {
                    src: ['node_modules/bootstrap-maxlength/dist/bootstrap-maxlength.min.js'],
                    dest: 'plugins/bootstrap-maxlength'
                },
                {
                    src: ['node_modules/chart.js/dist/chart.umd.js'],
                    dest: 'plugins/chartjs'
                },
                {
                    src: ['node_modules/clipboard/dist/clipboard.min.js'],
                    dest: 'plugins/clipboard'
                },
                {
                    src: ['node_modules/cropperjs/dist/cropper.min.js', 'node_modules/cropperjs/dist/cropper.min.css'],
                    dest: 'plugins/cropperjs'
                },
                {
                    src: ['node_modules/datatables.net/js/dataTables.min.js'],
                    dest: 'plugins/datatables.net'
                },
                {
                    src: ['node_modules/datatables.net-bs5/js/dataTables.bootstrap5.min.js', 'node_modules/datatables.net-bs5/css/dataTables.bootstrap5.min.css'],
                    dest: 'plugins/datatables.net-bs5'
                },
                {
                    src: ['node_modules/dropify/dist/*'],
                    dest: 'plugins/dropify'
                },
                {
                    src: ['node_modules/dropzone/dist/dropzone-min.js', 'node_modules/dropzone/dist/dropzone.css'],
                    dest: 'plugins/dropzone'
                },
                {
                    src: ['node_modules/easymde/dist/easymde.min.js', 'node_modules/easymde/dist/easymde.min.css'],
                    dest: 'plugins/easymde'
                },
                {
                    src: ['node_modules/flag-icons/css', 'node_modules/flag-icons/flags'],
                    dest: 'plugins/flag-icons'
                },
                {
                    src: ['node_modules/flatpickr/dist/flatpickr.min.js', 'node_modules/flatpickr/dist/flatpickr.min.css'],
                    dest: 'plugins/flatpickr'
                },
                {
                    src: ['node_modules/fullcalendar/index.global.min.js'],
                    dest: 'plugins/fullcalendar'
                },
                {
                    src: ['node_modules/inputmask/dist/jquery.inputmask.min.js'],
                    dest: 'plugins/inputmask'
                },
                {
                    src: ['node_modules/jquery-mousewheel/jquery.mousewheel.js'],
                    dest: 'plugins/jquery-mousewheel'
                },
                {
                    src: ['node_modules/jquery/dist/jquery.min.js'],
                    dest: 'plugins/jquery'
                },
                {
                    src: ['node_modules/jquery-steps/build/jquery.steps.min.js', 'node_modules/jquery-steps/demo/css/jquery.steps.css'],
                    dest: 'plugins/jquery-steps'
                },
                {
                    src: ['node_modules/jquery-tags-input/dist/*'],
                    dest: 'plugins/jquery-tags-input'
                },
                {
                    src: ['node_modules/jquery-validation/dist/jquery.validate.min.js'],
                    dest: 'plugins/jquery-validation'
                },
                {
                    src: ['node_modules/jquery.flot/*'],
                    dest: 'plugins/jquery.flot'
                },
                {
                    src: ['node_modules/jquery-sparkline/jquery.sparkline.min.js'],
                    dest: 'plugins/jquery-sparkline'
                },
                {
                    src: ['node_modules/lucide/dist/umd/lucide.min.js'],
                    dest: 'plugins/lucide'
                },
                {
                    src: ['node_modules/moment/min/moment.min.js'],
                    dest: 'plugins/moment'
                },
                {
                    src: ['node_modules/owl.carousel/dist/*'],
                    dest: 'plugins/owl-carousel'
                },
                {
                    src: ['node_modules/peity/jquery.peity.min.js'],
                    dest: 'plugins/peity'
                },
                {
                    src: ['node_modules/perfect-scrollbar/dist/*', 'node_modules/perfect-scrollbar/css/*'],
                    dest: 'plugins/perfect-scrollbar'
                },
                {
                    src: ['node_modules/@simonwep/pickr/dist/*'],
                    dest: 'plugins/pickr'
                },
                {
                    src: ['node_modules/prismjs/prism.js'],
                    dest: 'plugins/prismjs'
                },
                {
                    src: ['node_modules/prism-themes/themes/prism-coldark-dark.css'],
                    dest: 'plugins/prism-themes'
                },
                {
                    src: ['node_modules/select2/dist/js/select2.min.js', 'node_modules/select2/dist/css/select2.min.css'],
                    dest: 'plugins/select2'
                },
                {
                    src: ['node_modules/sortablejs/Sortable.min.js'],
                    dest: 'plugins/sortablejs'
                },
                {
                    src: ['node_modules/sweetalert2/dist/sweetalert2.min.js', 'node_modules/sweetalert2/dist/sweetalert2.min.css'],
                    dest: 'plugins/sweetalert2'
                },
                {
                    src: ['node_modules/tinymce/*'],
                    dest: 'plugins/tinymce'
                },
                {
                    src: ['node_modules/typeahead.js/dist/typeahead.bundle.min.js'],
                    dest: 'plugins/typeahead-js'
                },
            ]
        }),
    ],
    css: {
        devSourcemap: true,
        preprocessorOptions: {
            scss: {
                silenceDeprecations: ['mixed-decls', 'color-functions', 'global-builtin', 'import'],
            }
        }
    },
});
