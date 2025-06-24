import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
const plugin = require('tailwindcss/plugin');

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
        './resources/**/*.blade.php',
        './app/**/*.php',
    ],

        safelist: [
        'bg-blue-500', 'text-white',
        'bg-yellow-500', 'text-black',
        'bg-green-500',
        'bg-gray-300',

        // Funk custom colors
        'bg-funk-blue',
        'bg-funk-lt-blue',
        'bg-funk-md-blue',
        'bg-funk-orange',
        'bg-funk-yellow',
        'bg-funk-red',
        'bg-funk-green',
        'bg-funk-md-green',
        'bg-funk-lt-green',

        // Custom badge classes
        'badge',
        'badge-priority-low',
        'badge-priority-medium',
        'badge-priority-high',
        'badge-status-open',
        'badge-status-inprogress',
        'badge-status-closed',
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

                'priority-low': '#64748b',     // slate-500
                'priority-medium': '#0ea5e9',  // sky-500
                'priority-high': '#ef4444',    // red-500
                'status-open': '#0ea5e9',      // sky-500
                'status-inprogress': '#22c55e', // green-500
                'status-closed': '#6b7280',    // gray-500

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

    plugins: [
      plugin(function({ addComponents, theme }) {
        addComponents({
          '.badge': {
            display: 'inline-flex',
            alignItems: 'center',
            gap: '0.5rem',
            padding: '0.25rem 0.75rem',
            fontSize: theme('fontSize.xs')[0],
            fontWeight: theme('fontWeight.semibold'),
            borderRadius: theme('borderRadius.full'),
            borderWidth: '1px',
            lineHeight: '1',
          },
          '.badge-priority-low': {
            color: theme('colors.priority-low'),
            borderColor: theme('colors.priority-low'),
            backgroundColor: '#64748b1A', // 1A means 10% opacity
          },
          '.badge-priority-medium': {
            color: theme('colors.priority-medium'),
            borderColor: theme('colors.priority-medium'),
            backgroundColor: '#0ea5e91A', 
          },
          '.badge-priority-high': {
            color: theme('colors.priority-high'),
            borderColor: theme('colors.priority-high'),
            backgroundColor: '#ef44441A',
          },
          '.badge-status-open': {
            color: theme('colors.status-open'),
            borderColor: theme('colors.status-open'),
            backgroundColor: '#0ea5e91A',
          },
          '.badge-status-inprogress': {
            color: theme('colors.status-inprogress'),
            borderColor: theme('colors.status-inprogress'),
            backgroundColor: '#22c55e1A',
          },
          '.badge-status-closed': {
            color: theme('colors.status-closed'),
            borderColor: theme('colors.status-closed'),
            backgroundColor: '#6b72801A',
          },
        });
      }),
    ],

//    [forms],

};
