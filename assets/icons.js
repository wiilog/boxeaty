const icons = {
    operation: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 79"><path fill="currentColor" d="M77.25 78.61H2.75a2.5 2.5 0 01-2.5-2.5V2.89a2.5 2.5 0 012.5-2.5h74.5a2.5 2.5 0 012.5 2.5v73.22a2.5 2.5 0 01-2.5 2.5zm-72-5h69.5V5.39H5.25z"/><path fill="currentColor" d="M65.35 20h-26.5a2.5 2.5 0 010-5h26.5a2.5 2.5 0 010 5zM65.35 32h-26.5a2.5 2.5 0 110-5h26.5a2.5 2.5 0 010 5zM28.35 34.76H14.66a2.49 2.49 0 01-2.5-2.5V14.74a2.49 2.49 0 012.5-2.5h13.69a2.49 2.49 0 012.5 2.5v17.52a2.49 2.49 0 01-2.5 2.5zm-11.19-5h8.69V17.24h-8.69zM65.35 52h-26.5a2.5 2.5 0 110-5h26.5a2.5 2.5 0 010 5zM65.35 64h-26.5a2.5 2.5 0 110-5h26.5a2.5 2.5 0 010 5zM28.35 66.76H14.66a2.49 2.49 0 01-2.5-2.5V46.74a2.49 2.49 0 012.5-2.5h13.69a2.49 2.49 0 012.5 2.5v17.52a2.49 2.49 0 01-2.5 2.5zm-11.19-5h8.69V49.24h-8.69z"/></svg>`,
};

for (const element of document.getElementsByClassName('bxi')) {
    const icon = Array.from(element.classList)
        .find(clss => clss.startsWith(`bxi-`))
        .substring(4);

    element.insertAdjacentHTML(`beforebegin`, icons[icon]);
    element.previousSibling.classList = element.classList;
    element.remove();
}