import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 512 512"
            xmlns="http://www.w3.org/2000/svg"
        >
            {/* Estructura hexagonal (contorno). Usa currentColor para adaptarse al tema. */}
            <g
                fill="none"
                stroke="currentColor"
                strokeWidth={34}
                strokeLinejoin="round"
            >
                <path d="M256 70 416 162v188L256 442 96 350V162Z" />
            </g>

            {/* Núcleo central. */}
            <path
                fill="currentColor"
                fillRule="evenodd"
                d="M256 139 349 228 256 373 163 228 Z M256 207 A38 38 0 1 0 256 283 A38 38 0 1 0 256 207 Z"
            />
        </svg>
    );
}
