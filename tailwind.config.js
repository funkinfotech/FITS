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

        safelist: [
        'bg-blue-500', 'text-white',
        'bg-yellow-500', 'text-black',
        'bg-green-500',
        'bg-gray-300',
        'bg-funk-blue',
        'bg-funk-lt-blue',
        'bg-funk-md-blue',
        'bg-funk-orange',
        'bg-funk-yellow',
        'bg-funk-red',
        'bg-funk-green',
        'bg-funk-md-green',
        'bg-funk-lt-green',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {

                'funk-blue': '#052a44',
                'funk-lt-blue': '#4f7fa2',
                'funk-md-blue': '#3173a2',
                'funk-orange': '#b5532f',
                'funk-yellow': '#b5922f',
                'funk-red': '#6a1e01',
                'funk-green': '#014928',
                'funk-md-green': '#2ba46d',
                'funk-lt-green': '#4ba47c',

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
