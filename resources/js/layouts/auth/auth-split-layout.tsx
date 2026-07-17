import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="grid min-h-svh lg:grid-cols-[70%_30%]">
            {/* Lado izquierdo: imagen (cambia según el tema). */}
            <div className="relative hidden lg:block">
                <img
                    src="/assets/images/background/background_claro.png"
                    alt=""
                    className="absolute inset-0 h-full w-full object-cover dark:hidden"
                />
                <img
                    src="/assets/images/background/background_oscuro.png"
                    alt=""
                    className="absolute inset-0 hidden h-full w-full object-cover dark:block"
                />
            </div>

            {/* Lado derecho: formulario de login. */}
            <div className="flex flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
                <div className="w-full max-w-sm">
                    <div className="flex flex-col gap-8">
                        <div className="flex flex-col items-center gap-4">
                            <Link
                                href={home()}
                                className="flex flex-col items-center gap-2 font-medium"
                            >
                                <div className="mb-1 flex h-[49px] w-[49px] items-center justify-center rounded-md">
                                    <AppLogoIcon className="size-[49px] fill-current text-[var(--foreground)] dark:text-white" />
                                </div>
                                <span className="sr-only">{title}</span>
                            </Link>

                            <div className="space-y-2 text-center">
                                <h1 className="text-xl font-medium">{title}</h1>
                                <p className="text-center text-sm text-muted-foreground">
                                    {description}
                                </p>
                            </div>
                        </div>
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
