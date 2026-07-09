import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type DialogActionButtonProps = ComponentProps<typeof Button> & {
    processing?: boolean;
    processingLabel?: string;
};

export function DialogActionButton({
    processing = false,
    processingLabel,
    disabled,
    children,
    ...props
}: DialogActionButtonProps) {
    return (
        <Button disabled={disabled || processing} {...props}>
            {processing ? (
                <>
                    <Spinner />
                    {processingLabel ?? children}
                </>
            ) : (
                children
            )}
        </Button>
    );
}
