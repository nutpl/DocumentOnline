const documentArea = document.getElementById("document");

fetch(fieldsUrl)
    .then(res => res.json())
    .then(fields => {

        fields.forEach(field => {

            let element;

            switch (field.type) {

                case "textarea":

                    element = document.createElement("textarea");

                    break;

                default:

                    element = document.createElement("input");

                    element.type = "text";

            }

            element.className = "input-field";

            element.name = field.name;

            element.style.left = field.left + "px";
            element.style.top = field.top + "px";
            element.style.width = field.width + "px";

            if (field.height) {
                element.style.height = field.height + "px";
            }

            if (field.readonly) {
                element.readOnly = true;
            }

            documentArea.appendChild(element);

        });

    });