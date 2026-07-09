import { Check, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { DialogActionButton } from '@/components/dialog-action-button';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type CrudFormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    submitLabel?: string;
    processing?: boolean;
    onSubmit?: (event: React.FormEvent<HTMLFormElement>) => void;
    showFooter?: boolean;
    children: ReactNode;
    size?: 'sm' | 'md' | 'lg' | 'xl' | 'full';
    contentClassName?: string;
};

const sizeClasses: Record<NonNullable<CrudFormDialogProps['size']>, string> = {
    sm: 'w-full !max-w-md',
    md: 'w-full !max-w-2xl',
    lg: 'w-full !max-w-4xl',
    xl: 'w-full !max-w-6xl',
    full: 'w-[95vw] !max-w-[95vw]',
};

export function CrudFormDialog({
    open,
    onOpenChange,
    title,
    description,
    submitLabel = 'Guardar',
    processing = false,
    onSubmit,
    showFooter = true,
    children,
    size = 'md',
    contentClassName = '',
}: CrudFormDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                className={[
                    'max-h-[90vh] overflow-y-auto',
                    sizeClasses[size],
                    contentClassName,
                ].join(' ')}
            >
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                {onSubmit ? (
                    <form className="space-y-4" onSubmit={onSubmit}>
                        {children}
                        {showFooter && (
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="outline">
                                        {' '}
                                        <X className="mr-1 size-4" />
                                        Cerrar
                                    </Button>
                                </DialogClose>
                                <DialogActionButton
                                    type="submit"
                                    processing={processing}
                                >
                                    <Check className="mr-1 size-4" />
                                    {submitLabel}
                                </DialogActionButton>
                            </DialogFooter>
                        )}
                    </form>
                ) : (
                    <div className="space-y-4">{children}</div>
                )}
            </DialogContent>
        </Dialog>
    );
}
