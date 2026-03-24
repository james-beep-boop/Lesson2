{{-- Outer: fixed 2.5rem bottom gap regardless of page height (fi-simple-main-ctn was flex:1 and grew) --}}
<div style="padding:0 2.5rem 2.5rem;">
    <div class="fi-simple-main"
         style="text-align:center; color:var(--gray-500); font-size:0.75rem; line-height:1rem; padding-block:1.25rem; margin-block:0;">
        <p>
            Lesson Plans by
            <a href="https://areseducation.org" target="_blank" rel="noopener noreferrer"
               style="text-decoration:underline;">ARES Education</a>
            and
            <a href="https://www.seavuria.org" target="_blank" rel="noopener noreferrer"
               style="text-decoration:underline;">SeaVuria</a>
            &mdash;
            Copyright
            <a href="https://creativecommons.org/licenses/by-sa/4.0/deed.en" target="_blank" rel="noopener noreferrer"
               style="text-decoration:underline;">CC BY-SA 4.0</a>
            <span style="display:inline-flex;align-items:center;gap:2px;vertical-align:middle;margin-left:2px;">
                {{-- Creative Commons icon (Font Awesome, CC BY 4.0) --}}
                <svg style="display:inline-block;flex-shrink:0;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 496 512" width="14" height="14" aria-label="Creative Commons" fill="currentColor">
                    <path d="M245.83 214.87l-33.22 17.28c-9.43-19.58-25.24-19.93-27.46-19.93-22.13 0-33.22 14.61-33.22 43.84 0 23.57 9.21 43.84 33.22 43.84 14.47 0 24.65-7.09 30.57-21.26l30.55 15.5c-6.17 11.51-25.69 38.98-65.1 38.98-22.6 0-73.96-10.32-73.96-77.05 0-58.69 43-77.06 72.63-77.06 30.72-.01 52.7 11.95 65.99 35.86zm143.05 0l-32.78 17.28c-9.5-19.77-25.72-19.93-27.9-19.93-22.14 0-33.22 14.61-33.22 43.84 0 23.55 9.23 43.84 33.22 43.84 14.45 0 24.65-7.09 30.54-21.26l31 15.5c-2.1 3.75-21.39 38.98-65.09 38.98-22.69 0-73.96-9.87-73.96-77.05 0-58.67 42.97-77.06 72.63-77.06 30.71-.01 52.58 11.95 65.56 35.86zM247.56 8.05C104.74 8.05 0 123.11 0 256.05c0 138.49 113.6 248 247.56 248 129.93 0 248.44-100.87 248.44-248 0-137.87-106.62-248-248.44-248zm.87 450.81c-112.54 0-203.7-93.04-203.7-202.81 0-105.42 85.43-203.27 203.72-203.27 112.53 0 202.82 89.46 202.82 203.26-.01 121.69-99.68 202.82-202.84 202.82z"/>
                </svg>
                {{-- Attribution (BY) icon (Font Awesome, CC BY 4.0) --}}
                <svg style="display:inline-block;flex-shrink:0;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 496 512" width="14" height="14" aria-label="Attribution" fill="currentColor">
                    <path d="M314.9 194.4v101.4h-28.3v120.5h-77.1V295.9h-28.3V194.4c0-4.4 1.6-8.2 4.6-11.3 3.1-3.1 6.9-4.7 11.3-4.7H299c4.1 0 7.8 1.6 11.1 4.7 3.1 3.2 4.8 6.9 4.8 11.3zm-101.5-63.7c0-23.3 11.5-35 34.5-35s34.5 11.7 34.5 35c0 23-11.5 34.5-34.5 34.5s-34.5-11.5-34.5-34.5zM247.6 8C389.4 8 496 118.1 496 256c0 147.1-118.5 248-248.4 248C113.6 504 0 394.5 0 256 0 123.1 104.7 8 247.6 8zm.8 44.7C130.2 52.7 44.7 150.6 44.7 256c0 109.8 91.2 202.8 203.7 202.8 103.2 0 202.8-81.1 202.8-202.8.1-113.8-90.2-203.3-202.8-203.3z"/>
                </svg>
                {{-- Share-Alike (SA) icon (Font Awesome, CC BY 4.0) --}}
                <svg style="display:inline-block;flex-shrink:0;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 496 512" width="14" height="14" aria-label="Share Alike" fill="currentColor">
                    <path d="M247.6 8C389.4 8 496 118.1 496 256c0 147.1-118.5 248-248.4 248C113.6 504 0 394.5 0 256 0 123.1 104.7 8 247.6 8zm.8 44.7C130.2 52.7 44.7 150.6 44.7 256c0 109.8 91.2 202.8 203.7 202.8 103.2 0 202.8-81.1 202.8-202.8.1-113.8-90.2-203.3-202.8-203.3zM137.7 221c13-83.9 80.5-95.7 108.9-95.7 99.8 0 127.5 82.5 127.5 134.2 0 63.6-41 132.9-128.9 132.9-38.9 0-99.1-20-109.4-97h62.5c1.5 30.1 19.6 45.2 54.5 45.2 23.3 0 58-18.2 58-82.8 0-82.5-49.1-80.6-56.7-80.6-33.1 0-51.7 14.6-55.8 43.8h18.2l-49.2 49.2-49-49.2h19.4z"/>
                </svg>
            </span>
            <br>
            Adapt, transform, redistribute, provided appropriate credit is given.
        </p>
        <div style="margin-top:1rem;display:flex;justify-content:center;gap:0.5rem;">
            <x-filament::button
                tag="a"
                href="https://areseducation.org/donate.html"
                target="_blank"
                rel="noopener noreferrer"
                class="!w-auto shrink-0"
                style="white-space:nowrap;"
            >
                Donate to ARES Education
            </x-filament::button>
            <x-filament::button
                tag="a"
                href="https://www.seavuria.org/donate"
                target="_blank"
                rel="noopener noreferrer"
                class="!w-auto shrink-0"
                style="white-space:nowrap;"
            >
                Donate to SeaVuria
            </x-filament::button>
        </div>
    </div>
</div>
