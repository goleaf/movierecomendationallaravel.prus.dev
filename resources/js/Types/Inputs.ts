import type { ButtonHTMLAttributes, ComponentProps, ReactNode } from "react";

export interface InputFieldProps extends ComponentProps<"input"> {
    label?: string;
    errorMessage?: string;
    name: string;
    helperText?: string;
    wrapperClassName?: string;
}

export interface TextAreaProps extends ComponentProps<"textarea"> {
    label?: string;
    errorMessage?: string;
    name: string;
    helperText?: string;
    wrapperClassName?: string;
}

export interface ButtonProps
    extends Omit<
        ButtonHTMLAttributes<HTMLButtonElement>,
        "className" | "onDrag" | "onDragStart" | "onDragEnd" | "onAnimationStart" | "onAnimationEnd"
    > {
    className?: string;
    children: ReactNode;
    loading: boolean;
    isSuccess?: boolean;
    isError?: boolean;
}
