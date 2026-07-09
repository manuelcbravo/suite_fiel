import * as React from 'react';
import { cn } from '@/lib/utils';

function Field({ className, ...props }: React.ComponentProps<'div'>) {
    return <div className={cn('space-y-2', className)} {...props} />;
}

function FieldError({ className, ...props }: React.ComponentProps<'p'>) {
    return <p className={cn('text-sm text-destructive', className)} {...props} />;
}

export { Field, FieldError };
