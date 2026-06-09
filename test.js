
function t(v) {
        let s = String(v).trim().replace(/[\u2212\u2013\u2014]/g, "-");
        
        let sign = "";
        const lastMinus = s.lastIndexOf("-");
        const lastPlus = s.lastIndexOf("+");
        if (lastMinus > lastPlus) sign = "-";
        else if (lastPlus > lastMinus) sign = "+";
        else if (lastMinus !== -1 && lastMinus === lastPlus) sign = "";

        s = s.replace(/[^0-9.]/g, "");
        
        if (!s) return sign;
        if (!s.includes(".")) s = String(parseInt(s, 10));
        return sign + s;
}

console.log("-", t("-"));
console.log("0-", t("0-"));
console.log("-0", t("-0"));
console.log("-5", t("-5"));

