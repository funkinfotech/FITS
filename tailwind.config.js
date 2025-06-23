import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    DEFAULT: '#052A44',
                    50: '#e6eef2',
                    100: '#c1d6e1',
                    200: '#9dbfce',
                    300: '#78a7bc',
                    400: '#5480a1',
                    500: '#3c6082',
                    600: '#2d4b67',
                    700: '#1f364a',
                    800: '#11212e',
                    900: '#051019',
                },
            },
        },
    },

    plugins: [forms],
};
