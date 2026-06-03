/** Chuẩn hóa mảng { value, label } cho SearchSelect (valueKey=id, labelKey=name). */
export function valueLabelOptions(items = []) {
    return items.map((o) => ({ id: o.value, name: o.label }));
}
