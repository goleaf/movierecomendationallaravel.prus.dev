import ThemeContext from "@/Context/ThemeContext";
import { Theme } from "@/Types/Enums";
import type { LayoutProps } from "@/Types/Types";
import { type FC, useEffect, useMemo, useState } from "react";

const systemIsDark = () => {
    if (typeof window === "undefined") {
        return false;
    }

    return window?.matchMedia("(prefers-color-scheme: dark)").matches;
};

const localStorageHandler = {
    get: (key: string) => {
        if (typeof window === "undefined") {
            return null;
        }

        return localStorage.getItem(key);
    },
    set: (key: string, value: string) => {
        if (typeof window === "undefined") {
            return;
        }

        localStorage.setItem(key, value);
    },
};

const ThemeWrapper: FC<LayoutProps> = ({ children }) => {
    const THEME_KEY: string = "FLIRT_THEME";

    const [theme, setTheme] = useState<Theme>(() => {
        const stored = localStorageHandler.get(THEME_KEY);

        return (stored as Theme) || Theme.System;
    });

    const systemTheme = systemIsDark() ? Theme.Dark : Theme.Light;
    const isDarkMode = theme === Theme.Dark || (theme === Theme.System && systemTheme === Theme.Dark);
    const toggleTheme = () => {
        const nextTheme = {
            [Theme.Light]: Theme.Dark,
            [Theme.Dark]: Theme.System,
            [Theme.System]: Theme.Light,
        }[theme];

        setTheme(nextTheme);
    };

    useEffect(() => {
        const htmlElement = document.documentElement;

        htmlElement.classList.remove(Theme.Dark);

        if (theme === Theme.Dark || (theme === Theme.System && systemTheme === Theme.Dark)) {
            htmlElement.classList.add(Theme.Dark);
        }

        localStorageHandler.set(THEME_KEY, theme);
    }, [theme, systemTheme]);

    const contextValue = useMemo(
        () => ({
            theme,
            systemTheme,
            isDarkMode,
            setTheme,
            toggleTheme,
        }),
        [theme, systemTheme],
    );

    return <ThemeContext.Provider value={contextValue}>{children}</ThemeContext.Provider>;
};

export default ThemeWrapper;
